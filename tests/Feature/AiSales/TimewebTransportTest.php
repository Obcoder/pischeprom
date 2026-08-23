<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Timeweb\TimewebAiGatewayTransport;
use App\Infrastructure\AiSales\Timeweb\TimewebTransportException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;

class TimewebTransportTest extends Stage05TestCase
{
    public function test_transport_uses_exact_host_path_route_key_and_safe_guzzle_options(): void
    {
        $capturedOptions = [];
        Http::fake(function (Request $request, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return Http::response(['data' => [['id' => 'model/one', 'object' => 'model']]], 200, [
                'Content-Type' => 'application/json',
                'X-Request-ID' => 'safe-request-1',
            ]);
        });

        $response = app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);

        $this->assertSame('safe-request-1', $response->requestId);
        $this->assertSame('model/one', $response->data['data'][0]['id']);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('GET', $request->method());
            $this->assertSame('https://api.timeweb.ai/v1/models', $request->url());
            $this->assertSame(['Bearer stage05-local-route-fixture'], $request->header('Authorization'));
            $this->assertSame(['local_ru'], $request->header('X-AI-Processing-Route'));

            return true;
        });
        $this->assertFalse($capturedOptions['allow_redirects']);
        $this->assertTrue($capturedOptions['verify']);
        $this->assertFalse($capturedOptions['http_errors']);
        $this->assertSame(3, $capturedOptions['connect_timeout']);
        $this->assertSame(10, $capturedOptions['timeout']);
        $this->assertSame(10, $capturedOptions['read_timeout']);
        $this->assertIsCallable($capturedOptions['progress']);

        try {
            $capturedOptions['progress'](0, 1_048_577, 0, 0);
            $this->fail('Chunked responses must be stopped at the byte cap.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('timeweb_response_too_large', $exception->getMessage());
        }
    }

    public function test_base_url_route_key_and_probe_guards_fail_before_http(): void
    {
        Http::fake();
        foreach ([
            'http://api.timeweb.ai/v1',
            'https://api.timeweb.ai.evil.test/v1',
            'https://api.timeweb.ai:444/v1',
            'https://api.timeweb.ai/v2',
            'https://user@api.timeweb.ai/v1',
            'https://api.timeweb.ai/v1?target=evil',
            'https://api.timeweb.ai/v1#fragment',
        ] as $invalidUrl) {
            config()->set('ai-sales.providers.timeweb.base_url', $invalidUrl);

            try {
                app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
                $this->fail('An unapproved Base URL must be rejected.');
            } catch (PolicyViolation $violation) {
                $this->assertSame('timeweb_base_url_blocked', $violation->errorCode);
            }
        }

        Http::assertNothingSent();
        config()->set('ai-sales.providers.timeweb.base_url', 'https://api.timeweb.ai/v1');
        config()->set('ai-sales.providers.timeweb.routes.external_sanitized.api_key', 'stage05-local-route-fixture');

        try {
            app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
            $this->fail('Shared contour keys must be rejected.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_route_keys_not_separated', $violation->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_credential_shaped_response_header_is_not_retained_as_request_id(): void
    {
        Http::fake(['https://api.timeweb.ai/v1/models' => Http::response(
            ['data' => []],
            200,
            ['Content-Type' => 'application/json', 'X-Request-ID' => 'stage05-local-route-fixture'],
        )]);

        $response = app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);

        $this->assertNull($response->requestId);
    }

    public function test_outer_feature_flags_failover_and_kill_switches_block_before_http(): void
    {
        Http::fake();
        config()->set('ai-sales.transport_mode', 'fake_only');

        try {
            app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
            $this->fail('Timeweb requires the explicit synthetic-only transport mode.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_route_disabled', $violation->errorCode);
        }

        config()->set('ai-sales.transport_mode', 'timeweb_synthetic_only');
        config()->set('ai-sales.provider_failover_enabled', true);

        try {
            app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
            $this->fail('Timeweb probes require failover to remain disabled.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_failover_must_be_disabled', $violation->errorCode);
        }

        config()->set('ai-sales.provider_failover_enabled', false);
        config()->set('ai-sales.providers.timeweb.probe.max_rub', '100.0001');

        try {
            app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
            $this->fail('An excessive PoC RUB cap must fail closed.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_probe_budget_missing', $violation->errorCode);
        }

        config()->set('ai-sales.providers.timeweb.probe.max_rub', '10.0000');
        DB::table('ai_control_settings')->where('key', 'kill_switch.global')->update(['boolean_value' => true]);

        try {
            app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
            $this->fail('An active global kill switch must block Timeweb before HTTP.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('ai_kill_switch_active', $violation->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_model_and_provider_state_are_rejected_at_transport_boundary(): void
    {
        Http::fake();
        $transport = app(TimewebAiGatewayTransport::class);

        foreach ([
            [
                'model' => 'attacker/model',
                'messages' => [],
                'stream' => false,
                'store' => false,
            ],
            [
                'model' => 'external/synthetic-terra',
                'messages' => [],
                'stream' => false,
                'store' => false,
                'previous_response_id' => 'provider-state-is-forbidden',
            ],
        ] as $payload) {
            try {
                $transport->chatCompletions(AiProviderRoute::ExternalSanitized, $payload);
                $this->fail('Caller-controlled model or provider state must be rejected.');
            } catch (PolicyViolation $violation) {
                $this->assertContains($violation->errorCode, [
                    'timeweb_model_not_allowlisted',
                    'timeweb_wire_payload_blocked',
                ]);
            }
        }

        Http::assertNothingSent();
    }

    public function test_transport_rejects_redirect_content_type_invalid_json_and_oversized_body(): void
    {
        config()->set('ai-sales.providers.timeweb.max_response_bytes', 1024);
        $cases = [
            [AiProviderErrorCategory::ProviderUnavailable, 'timeweb_http_failure'],
            [AiProviderErrorCategory::InvalidResponse, 'timeweb_unexpected_content_type'],
            [AiProviderErrorCategory::InvalidResponse, 'timeweb_invalid_json'],
            [AiProviderErrorCategory::OversizedResponse, 'timeweb_response_too_large'],
        ];
        Http::fakeSequence()
            ->push('', 302, ['Content-Type' => 'application/json', 'Location' => 'https://evil.test'])
            ->push('<html>no</html>', 200, ['Content-Type' => 'text/html'])
            ->push('{broken', 200, ['Content-Type' => 'application/json'])
            ->push(['data' => [['id' => str_repeat('x', 1500)]]], 200, ['Content-Type' => 'application/json']);

        foreach ($cases as [$category, $code]) {
            try {
                app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::ExternalSanitized);
                $this->fail('Invalid transport response must be rejected.');
            } catch (TimewebTransportException $exception) {
                $this->assertSame($category, $exception->category);
                $this->assertSame($code, $exception->safeCode);
                $this->assertStringNotContainsString('stage05-external-route-fixture', $exception->getMessage());
            }
        }
    }

    public function test_http_status_taxonomy_discards_provider_body_and_credentials(): void
    {
        Log::spy();
        $expected = [
            400 => AiProviderErrorCategory::BadRequest,
            401 => AiProviderErrorCategory::Authentication,
            402 => AiProviderErrorCategory::InsufficientBalance,
            404 => AiProviderErrorCategory::NotFound,
            405 => AiProviderErrorCategory::UnsupportedEndpoint,
            409 => AiProviderErrorCategory::Conflict,
            422 => AiProviderErrorCategory::Unprocessable,
            429 => AiProviderErrorCategory::RateLimited,
            503 => AiProviderErrorCategory::ServerError,
        ];

        $sequence = Http::sequence();

        foreach (array_keys($expected) as $status) {
            $sequence->push([
                'error' => ['message' => 'provider-body-must-not-escape'],
            ], $status, ['Content-Type' => 'application/json']);
        }

        Http::fake(['*' => $sequence]);

        foreach ($expected as $status => $category) {
            try {
                app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::ExternalSanitized);
                $this->fail("HTTP {$status} must fail.");
            } catch (TimewebTransportException $exception) {
                $this->assertSame($category, $exception->category);
                $safeState = $exception->getMessage().$exception->safeCode.($exception->requestId ?? '');
                $this->assertStringNotContainsString('provider-body-must-not-escape', $safeState);
                $this->assertStringNotContainsString('stage05-external-route-fixture', $safeState);
            }
        }

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
    }

    #[DataProvider('connectionFailureProvider')]
    public function test_connection_failures_are_normalized_without_raw_exception_text(
        string $wireMessage,
        AiProviderErrorCategory $expected,
        string $safeCode,
    ): void {
        Http::fake(['*' => Http::failedConnection($wireMessage)]);

        try {
            app(TimewebAiGatewayTransport::class)->listModels(AiProviderRoute::LocalRu);
            $this->fail('Connection failure must be normalized.');
        } catch (TimewebTransportException $exception) {
            $this->assertSame($expected, $exception->category);
            $this->assertSame($safeCode, $exception->safeCode);
            $this->assertStringNotContainsString($wireMessage, $exception->getMessage());
        }
    }

    public static function connectionFailureProvider(): array
    {
        return [
            'dns' => ['Could not resolve host private-diagnostic.invalid', AiProviderErrorCategory::Dns, 'timeweb_dns_failure'],
            'timeout' => ['Connection timed out private-diagnostic', AiProviderErrorCategory::Timeout, 'timeweb_timeout'],
            'tls' => ['SSL certificate problem private-diagnostic', AiProviderErrorCategory::Tls, 'timeweb_tls_failure'],
        ];
    }
}
