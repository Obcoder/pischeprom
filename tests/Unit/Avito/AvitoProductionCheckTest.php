<?php

namespace Tests\Unit\Avito;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;
use Tests\TestCase;

use function AvitoProductionCheck\inspect;
use function AvitoProductionCheck\listingApiNotice;
use function AvitoProductionCheck\listingResponseSummary;
use function AvitoProductionCheck\providerListings;
use function AvitoProductionCheck\providerSubscriptions;
use function AvitoProductionCheck\safeDiagnostics;
use function AvitoProductionCheck\subscriptionSummary;
use function AvitoProductionCheck\workerSummary;

require_once dirname(__DIR__, 3).'/scripts/check-production-avito.php';

class AvitoProductionCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        Http::preventStrayRequests();
        config([
            'avito.enabled' => true,
            'avito.client_id' => 'test-client',
            'avito.client_secret' => 'test-private-secret',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.timeout_seconds' => 30,
            'avito.connect_timeout_seconds' => 10,
        ]);
    }

    public function test_github_notice_contains_only_typed_listing_health_metadata(): void
    {
        $notice = listingApiNotice(['listing_api' => [
            'source' => 'client_credentials', 'credentials_configured' => true, 'inspection' => 'complete',
            'account' => ['inspection' => 'complete', 'http_status' => 200, 'response_valid' => true, 'id' => 123456, 'name' => 'Private account'],
            'listings' => ['inspection' => 'provider_error', 'http_status' => 403, 'response_valid' => false, 'message' => 'Private provider error'],
        ], 'accounts' => ['Private unrelated data']]);

        $this->assertSame('::notice title=Avito listing API::{"inspection":"complete","credentials_configured":true,"account":{"inspection":"complete","http_status":200,"response_valid":true},"listings":{"inspection":"provider_error","http_status":403,"response_valid":false}}', $notice);
    }

    public function test_github_notice_rejects_arbitrary_strings_command_injection_and_untyped_values(): void
    {
        $injection = "private-value%0A\n::error title=private::Private secret";
        $notice = listingApiNotice(['listing_api' => [
            'credentials_configured' => 'true', 'inspection' => $injection,
            'account' => ['inspection' => ['complete'], 'http_status' => '200', 'response_valid' => 1, 'error_category' => $injection],
            'listings' => ['inspection' => $injection, 'http_status' => 600, 'response_valid' => $injection],
        ]]);

        $this->assertSame(listingApiNotice(null), $notice);
        $this->assertSame(listingApiNotice([]), listingApiNotice(['listing_api' => $injection]));
        foreach (['private', 'Private', '::error', '%0A', "\n", '600', '200'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $notice);
        }
        $this->assertSame('::notice title=Avito listing API::{"inspection":"unknown","credentials_configured":null,"account":{"inspection":"unknown","http_status":null,"response_valid":null},"listings":{"inspection":"unknown","http_status":null,"response_valid":null}}', $notice);
    }

    public function test_optional_github_summary_preserves_default_json_and_never_echoes_arguments(): void
    {
        $arguments = [PHP_BINARY, base_path('scripts/check-production-avito.php'), '/nonexistent/private-deploy-path'];
        $default = new Process($arguments);
        $default->run();
        $this->assertSame(1, $default->getExitCode());
        $this->assertSame(['inspection' => 'unavailable', 'error_category' => 'application_bootstrap_failed'], json_decode($default->getOutput(), true));
        $this->assertStringNotContainsString('::notice', $default->getOutput());

        $summary = new Process([...$arguments, '--github-summary']);
        $summary->run();
        $this->assertSame(1, $summary->getExitCode());
        $this->assertSame($default->getOutput().listingApiNotice(null).PHP_EOL, $summary->getOutput());
        $this->assertStringNotContainsString('private-deploy-path', $summary->getOutput());
        $this->assertSame('', $summary->getErrorOutput());
    }

    public function test_listing_health_reads_only_the_personal_account_and_one_listing_without_exposing_data(): void
    {
        $options = [];
        Http::fake(function (Request $request, array $requestOptions) use (&$options) {
            $options[$request->url()] = $requestOptions;

            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/token' => Http::response(['access_token' => 'private-bearer-token', 'expires_in' => 86400]),
                '/core/v1/accounts/self' => Http::response(['id' => 99988, 'name' => 'Private account', 'email' => 'private@example.test']),
                '/core/v1/items' => Http::response(['resources' => [['id' => 7654321, 'title' => 'Private listing', 'price' => 1234, 'url' => 'https://avito.ru/private-listing']]]),
                default => throw new \RuntimeException('Unexpected HTTP request'),
            };
        });

        $result = providerListings();

        $this->assertSame([
            'source' => 'client_credentials',
            'credentials_configured' => true,
            'account' => ['inspection' => 'complete', 'http_status' => 200, 'response_valid' => true],
            'listings' => ['inspection' => 'complete', 'http_status' => 200, 'response_valid' => true],
            'inspection' => 'complete',
        ], $result);
        foreach (['private', 'Private', '99988', '7654321', '1234', 'avito.ru'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, json_encode($result));
        }
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/core/v1/items?per_page=1&page=1'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer private-bearer-token')
            && ! $request->hasHeader('X-AgencyClientId'));
        foreach ($options as $url => $requestOptions) {
            $this->assertSame(10, $requestOptions['timeout']);
            $this->assertSame(4, $requestOptions['connect_timeout']);
            if (! str_ends_with($url, '/token')) {
                $this->assertFalse($requestOptions['allow_redirects']);
            }
        }
        $this->assertSame(30, config('avito.timeout_seconds'));
        $this->assertSame(10, config('avito.connect_timeout_seconds'));
    }

    public function test_listing_health_never_falls_back_to_oauth_or_calls_provider_when_disabled_or_out_of_time(): void
    {
        Http::fake();
        config(['avito.client_id' => null, 'avito.client_secret' => null]);
        $this->assertSame('credentials_unavailable', providerListings()['inspection']);
        config(['avito.enabled' => false]);
        $this->assertSame('integration_disabled', providerListings()['inspection']);
        config(['avito.enabled' => true, 'avito.client_id' => 'test-client', 'avito.client_secret' => 'test-private-secret']);
        $this->assertSame('time_budget_exceeded', providerListings(microtime(true) + 1)['inspection']);
        Http::assertNothingSent();
    }

    public function test_listing_health_does_not_count_invalid_or_failed_provider_responses_as_healthy(): void
    {
        $this->assertSame('invalid_response', listingResponseSummary('account', 200, ['id' => 0])['inspection']);
        $this->assertSame('invalid_response', listingResponseSummary('listings', 200, ['resources' => ['private-id' => []]])['inspection']);
        $this->assertSame('invalid_response', listingResponseSummary('listings', 200, ['message' => 'private-error'])['inspection']);
        $this->assertSame('complete', listingResponseSummary('listings', 200, ['resources' => []])['inspection']);
        $this->assertSame([
            'inspection' => 'provider_error', 'http_status' => 403, 'response_valid' => false,
        ], listingResponseSummary('listings', 403, ['resources' => [], 'message' => 'private-error']));
    }

    public function test_listing_health_hides_authorization_errors_and_restores_token_timeouts(): void
    {
        Http::fake(['https://api.avito.ru/token' => Http::response(['error_description' => 'Private secret account details'], 403)]);

        $result = providerListings();

        $this->assertSame('unavailable', $result['inspection']);
        $this->assertSame('authorization', $result['error_category']);
        $this->assertStringNotContainsString('Private', json_encode($result));
        $this->assertSame(30, config('avito.timeout_seconds'));
        $this->assertSame(10, config('avito.connect_timeout_seconds'));
        Http::assertSentCount(1);
    }

    public function test_subscription_read_preserves_counts_and_respects_the_shared_provider_deadline(): void
    {
        Schema::create('avito_connections', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active');
        });
        config(['avito.webhook_secret' => 'private-callback-secret']);
        URL::forceRootUrl('https://shop.example');
        URL::forceScheme('https');
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'private-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/subscriptions' => Http::response([
                'subscriptions' => [['url' => route('api.avito.webhook').'?secret=private-callback-secret']],
            ]),
        ]);

        $result = providerSubscriptions(microtime(true) + 90);

        $this->assertSame(1, $result['configured_source_count']);
        $this->assertSame(0, $result['sources_omitted_by_limit']);
        $this->assertSame(1, $result['sources'][0]['matching_current_secret_count']);
        $this->assertStringNotContainsString('private-', json_encode($result));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/messenger/v1/subscriptions' && $request->method() === 'POST');
        $this->assertSame('time_budget_exceeded', providerSubscriptions(microtime(true) + 1)['sources'][0]['inspection']);
        Http::assertSentCount(2);
    }

    public function test_provider_report_only_counts_the_current_protected_callback(): void
    {
        $secret = 'private-value-never-print';
        $base = 'https://shop.example/api/avito/webhook';
        $result = subscriptionSummary([
            ['url' => $base.'?secret='.$secret],
            ['url' => $base.'?secret=old-private-secret'],
            ['url' => $base.'?secret[]=invalid'],
            ['url' => 'https://shop.example.evil.test/api/avito/webhook?secret='.$secret],
            ['url' => 'https://shop.example:8443/api/avito/webhook?secret='.$secret],
            ['url' => 'https://user:pass@shop.example/api/avito/webhook?secret='.$secret],
            ['url' => 'http://shop.example/api/avito/webhook?secret='.$secret],
            ['url' => ['invalid']],
        ], $base, $secret);

        $this->assertSame(['subscription_count' => 8, 'matching_endpoint_count' => 3, 'matching_current_secret_count' => 1], $result);
        $this->assertStringNotContainsString($secret, json_encode($result));
        $this->assertStringNotContainsString('shop.example', json_encode($result));
    }

    public function test_diagnostics_output_excludes_account_names_and_messages(): void
    {
        $result = safeDiagnostics([
            'mode' => 'active', 'response_mode' => 'assistant',
            'blockers' => [['code' => 'write_scope_unconfirmed', 'message' => 'Private name email@example.test']],
            'warnings' => [['code' => 'email@example.test', 'message' => 'secret-value']],
            'accounts' => [['id' => 2, 'name' => 'Private account name', 'sync_enabled' => true, 'sync_status' => 'success', 'credentials_ready' => true, 'write_scope_present' => null, 'access_token' => 'private-token']],
            'raw_exception' => 'private-exception', 'rules' => ['total' => 8, 'response_text' => 'Private answer'],
        ]);

        $this->assertSame('active', $result['mode']);
        $this->assertSame(['write_scope_unconfirmed'], $result['blockers']);
        $this->assertSame(['unknown'], $result['warnings']);
        $this->assertSame(['total' => 8], $result['rule_counts']);
        foreach (['Private', 'private-', 'email@', 'secret-value', 'response_text', 'access_token', 'raw_exception'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, json_encode($result));
        }
    }

    public function test_workers_are_matched_by_connection_and_queue_without_exposing_arguments(): void
    {
        $defaults = ['redis' => 'default', 'database' => 'default'];
        $default = workerSummary(['/usr/bin/php', '/srv/shop/artisan', 'queue:work', '--timeout=1800', '--unrelated-secret=private-value'], 'redis', $defaults);
        $this->assertTrue($default['consumes_avito_queue']);
        $this->assertSame(1800, $default['worker_default_timeout_seconds']);
        $this->assertStringNotContainsString('private-value', json_encode($default));
        $this->assertStringNotContainsString('/srv/shop', json_encode($default));

        $realtime = workerSummary(['php', 'artisan', 'queue:work', 'database', '--queue=realtime', '--timeout', '30'], 'redis', $defaults);
        $this->assertFalse($realtime['consumes_avito_queue']);
        $this->assertTrue($realtime['consumes_realtime_queue']);
        $this->assertSame(30, $realtime['worker_default_timeout_seconds']);

        $mail = workerSummary(['php', 'artisan', 'queue:work', '--queue', 'mail-sync,mail-notifications'], 'redis', $defaults);
        $this->assertFalse($mail['consumes_avito_queue']);
        $this->assertNull(workerSummary(['sh', '-c', 'php artisan queue:work'], 'redis', $defaults));
        $this->assertNull(workerSummary(['php', 'artisan', 'schedule:run'], 'redis', $defaults));
    }

    public function test_partial_inspection_is_not_promoted_to_complete_and_exception_is_hidden(): void
    {
        $this->assertSame(['inspection' => 'partial'], inspect(static fn () => ['inspection' => 'partial']));
        $this->assertSame(['inspection' => 'unavailable'], inspect(static fn () => throw new \RuntimeException('secret-value')));
    }
}
