<?php

namespace Tests\Unit;

use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Domain\AiSales\Web\PublicFetchPolicy;
use App\Domain\AiSales\Web\PublicPageTextExtractor;
use App\Domain\AiSales\Web\PublicUrlNormalizer;
use App\Domain\AiSales\Web\ResolvedPublicUrl;
use App\Services\Mail\MailPublicWebsiteReader;
use App\Services\Mail\MailResearchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class MailPublicWebsiteReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        $this->app->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'catalog.example.com' => ['93.184.216.34'],
        ]));
    }

    #[DataProvider('robotCases')]
    public function test_robot_groups_and_most_specific_paths_control_page_fetches(string $robots, string $path, bool $allowed): void
    {
        $url = 'https://catalog.example.com'.$path;
        Http::fake([
            'https://catalog.example.com/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
            $url => Http::response('<html><body><h1>Пектин</h1><p>Пищевая продукция</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        try {
            $result = app(MailPublicWebsiteReader::class)->read($url);
            $this->assertTrue($allowed, 'A robots-disallowed page was fetched.');
            $this->assertCount(1, $result['pages']);
            $this->assertSame($url, $result['pages'][0]['url']);
            Http::assertSentCount(2);
        } catch (MailResearchException $exception) {
            if ($allowed) {
                throw $exception;
            }
            Http::assertSentCount(1);
            Http::assertNotSent(fn ($request) => $request->url() === $url);
        }
    }

    public static function robotCases(): array
    {
        return [
            'utf8 bom does not hide the first user agent' => ["\xEF\xBB\xBFUser-agent: *\nDisallow: /", '/catalog', false],
            'all adjacent agents share their rules' => ["User-agent: *\nUser-agent: OtherBot\nDisallow: /", '/catalog', false],
            'named bot shares a multi-agent group' => ["User-agent: PischepromCatalog\nUser-agent: OtherBot\nDisallow: /catalog", '/catalog', false],
            'more specific allow wins' => ["User-agent: *\nDisallow: /\nAllow: /catalog", '/catalog', true],
            'more specific disallow wins' => ["User-agent: *\nAllow: /catalog\nDisallow: /catalog/private", '/catalog/private', false],
            'allow wins equal paths regardless of order' => ["User-agent: *\nAllow: /catalog\nDisallow: /catalog", '/catalog', true],
            'exact end anchor blocks its path' => ["User-agent: *\nDisallow: /catalog$", '/catalog', false],
            'exact end anchor permits a longer path' => ["User-agent: *\nDisallow: /catalog$", '/catalog/items', true],
            'wildcards match query strings' => ["User-agent: *\nDisallow: /*?q=*", '/catalog?q=test', false],
            'specific bot group overrides wildcard fallback' => ["User-agent: *\nDisallow: /\n\nUser-agent: PischepromCatalog\nDisallow: /private", '/catalog', true],
            'matching groups are combined' => ["User-agent: PischepromCatalog\nDisallow: /catalog\n\nUser-agent: PischepromCatalog\nAllow: /catalog", '/catalog', true],
            'empty disallow does not carry agents into the next group' => ["User-agent: *\nDisallow:\nUser-agent: OtherBot\nDisallow: /", '/catalog', true],
            'rules after the old hundred-rule boundary still apply' => ["User-agent: *\n".str_repeat("Allow: /elsewhere\n", 101).'Disallow: /catalog', '/catalog', false],
        ];
    }

    public function test_expired_budget_after_dns_check_never_starts_an_http_request(): void
    {
        Http::fake();
        $policy = Mockery::mock(PublicFetchPolicy::class);
        $policy->shouldReceive('assertDnsStable')->once()->andReturnUsing(static function (): void {
            usleep(40000);
        });
        $reader = new MailPublicWebsiteReader($policy, app(PublicUrlNormalizer::class), app(PublicPageTextExtractor::class));
        $target = new ResolvedPublicUrl('https://catalog.example.com/catalog', 'catalog.example.com', 'example.com', ['93.184.216.34']);

        try {
            (new ReflectionMethod($reader, 'request'))->invoke($reader, $target, 1024, microtime(true) + 0.02);
            $this->fail('Expired DNS budget must prevent the HTTP request.');
        } catch (MailResearchException $exception) {
            $this->assertStringContainsString('Время сканирования истекло', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_http_timeout_uses_budget_remaining_after_dns(): void
    {
        $capturedTimeout = null;
        Http::fake(function ($request, array $options) use (&$capturedTimeout) {
            $capturedTimeout = $options['timeout'];

            return Http::response('ok', 200);
        });
        $policy = Mockery::mock(PublicFetchPolicy::class);
        $policy->shouldReceive('assertDnsStable')->once()->ordered()->andReturnUsing(static function (): void {
            usleep(50000);
        });
        $policy->shouldReceive('pinnedTransportOptions')->once()->ordered()->andReturn(['allow_redirects' => false]);
        $policy->shouldReceive('assertDnsStable')->once()->ordered();
        $reader = new MailPublicWebsiteReader($policy, app(PublicUrlNormalizer::class), app(PublicPageTextExtractor::class));
        $target = new ResolvedPublicUrl('https://catalog.example.com/catalog', 'catalog.example.com', 'example.com', ['93.184.216.34']);

        (new ReflectionMethod($reader, 'request'))->invoke($reader, $target, 1024, microtime(true) + 0.5);

        $this->assertNotNull($capturedTimeout);
        $this->assertGreaterThan(0, $capturedTimeout);
        $this->assertLessThan(0.47, $capturedTimeout);
        Http::assertSentCount(1);
    }
}
