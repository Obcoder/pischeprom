<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Domain\AiSales\Web\PublicFetchPolicy;
use App\Domain\AiSales\Web\PublicUrlNormalizer;
use App\Models\ProspectingSearchResult;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class PublicWebFetchSecurityTest extends Stage09TestCase
{
    protected bool $allowExpectedHttpRequests = true;

    public function test_private_dns_is_blocked_before_any_http_request(): void
    {
        [$actor, $result] = $this->resultFixture();
        app()->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'buyer.synthetic.example' => ['127.0.0.1'],
        ]));

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/search-results/{$result->public_id}/fetch", [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'private_or_reserved_dns_blocked');

        Http::assertNothingSent();
    }

    public function test_dns_rebinding_is_blocked_before_transport(): void
    {
        [$actor, $result] = $this->resultFixture();
        app()->instance(PublicDnsResolver::class, new class extends PublicDnsResolver
        {
            private int $calls = 0;

            public function resolve(string $host): array
            {
                $this->calls++;

                return $this->calls <= 2 ? ['93.184.216.34'] : ['169.254.169.254'];
            }
        });

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/search-results/{$result->public_id}/fetch", [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'dns_rebinding_blocked');

        Http::assertNothingSent();
    }

    public function test_cross_host_redirect_is_blocked_even_within_the_same_registrable_domain(): void
    {
        [$actor, $result] = $this->resultFixture();
        Http::fake([
            'https://buyer.synthetic.example/robots.txt' => Http::response('', 404, ['Content-Type' => 'text/plain']),
            'https://buyer.synthetic.example/about' => Http::response('', 302, [
                'Location' => 'https://catalog.synthetic.example/company',
            ]),
        ]);

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/search-results/{$result->public_id}/fetch", [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cross_domain_redirect_blocked');

        Http::assertSentCount(2);
    }

    public function test_robots_disallow_blocks_page_request(): void
    {
        [$actor, $result] = $this->resultFixture();
        Http::fake([
            'https://buyer.synthetic.example/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /about",
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/search-results/{$result->public_id}/fetch", [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'robots_disallow_blocked');

        Http::assertSentCount(1);
    }

    public function test_connection_failure_is_normalized_without_retry_or_raw_error_persistence(): void
    {
        [$actor, $result] = $this->resultFixture();
        Http::fake([
            'https://buyer.synthetic.example/robots.txt' => Http::response('', 404, ['Content-Type' => 'text/plain']),
            'https://buyer.synthetic.example/about' => Http::failedConnection('synthetic socket detail must not persist'),
        ]);

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/search-results/{$result->public_id}/fetch", [])
            ->assertStatus(502)
            ->assertJsonPath('code', 'public_fetch_connection_failed');

        $this->assertDatabaseHas('prospecting_public_fetches', [
            'prospecting_search_result_id' => $result->id,
            'status' => 'blocked',
            'error_category' => 'network',
            'error_code' => 'public_fetch_connection_failed',
        ]);
        $stored = json_encode($result->fresh()->publicFetch->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('synthetic socket detail', $stored);
        Http::assertSentCount(2);
    }

    #[DataProvider('unsafePageResponses')]
    public function test_content_type_size_compression_and_dtd_guards(
        string $body,
        array $headers,
        string $safeCode,
        ?int $maxBytes = null,
    ): void {
        [$actor, $result] = $this->resultFixture();
        if ($maxBytes !== null) {
            config()->set('ai-sales.prospecting.limits.max_public_fetch_bytes', $maxBytes);
        }
        Http::fake([
            'https://buyer.synthetic.example/robots.txt' => Http::response('', 404, ['Content-Type' => 'text/plain']),
            'https://buyer.synthetic.example/about' => Http::response($body, 200, $headers),
        ]);

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/search-results/{$result->public_id}/fetch", [])
            ->assertUnprocessable()
            ->assertJsonPath('code', $safeCode);

        $this->assertDatabaseHas('prospecting_public_fetches', [
            'prospecting_search_result_id' => $result->id,
            'status' => 'blocked',
            'error_code' => $safeCode,
        ]);
        Http::assertSentCount(2);
    }

    public static function unsafePageResponses(): array
    {
        return [
            'PDF is not a Stage 09 content type' => [
                '%PDF synthetic', ['Content-Type' => 'application/pdf'], 'public_fetch_content_type_blocked',
            ],
            'declared oversized response' => [
                '<html></html>', ['Content-Type' => 'text/html', 'Content-Length' => '999999'], 'public_fetch_body_too_large',
            ],
            'compressed response' => [
                'compressed', ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip'], 'public_fetch_compression_blocked',
            ],
            'actual oversized response' => [
                str_repeat('x', 65), ['Content-Type' => 'text/plain'], 'public_fetch_body_too_large', 64,
            ],
            'DTD or entity declaration' => [
                '<!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><html>&xxe;</html>',
                ['Content-Type' => 'text/html'],
                'page_dtd_blocked',
            ],
        ];
    }

    public function test_url_query_sanitization_and_per_domain_budget_are_code_owned(): void
    {
        $normalized = app(PublicUrlNormalizer::class)->normalize(
            'https://company.example/about?token=must-not-survive&utm_source=test&id=7&q=pectin#fragment',
        );
        $this->assertSame('https://company.example/about?id=7&q=pectin', $normalized);
        $this->assertStringNotContainsString('token', $normalized);
        $this->assertStringNotContainsString('must-not-survive', $normalized);

        $policy = app(PublicFetchPolicy::class);
        for ($index = 0; $index < 5; $index++) {
            $policy->reserveDomainPage('company.example');
        }
        try {
            $policy->reserveDomainPage('company.example');
            $this->fail('Per-domain page budget was not enforced.');
        } catch (SearchProviderException $exception) {
            $this->assertSame('per_domain_page_budget_blocked', $exception->safeCode);
        }

        Http::assertNothingSent();
    }

    /** @return array{0: User, 1: ProspectingSearchResult} */
    private function resultFixture(): array
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedPlannedJob($actor);
        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/jobs/{$job->public_id}/search-execute", [])
            ->assertAccepted();
        $result = $job->searchResults()
            ->where('canonical_url', 'https://buyer.synthetic.example/about')
            ->whereNull('duplicate_of_id')
            ->firstOrFail();

        return [$actor, $result];
    }
}
