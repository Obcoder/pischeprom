<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Providers\TimewebExternalSanitizedProvider;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticDlpGuard;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticFixtureRegistry;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticRequestFactory;
use App\Models\AiProviderCapability;
use App\Models\AiProviderModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Fixtures\AiSales\TimewebDlpCanaries;

class TimewebSyntheticProbeTest extends Stage05TestCase
{
    public function test_all_external_probes_normalize_and_persist_safe_evidence_only(): void
    {
        $model = 'external/synthetic-terra';
        $this->inventory(AiProviderRoute::ExternalSanitized, $model);
        $this->pricing(AiProviderRoute::ExternalSanitized, $model);
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = mb_strtolower($query->sql);
        });
        $requestNumber = 0;
        $sent = [];
        Http::fake(function (Request $request) use (&$requestNumber, &$sent, $model) {
            $requestNumber++;
            $sent[] = ['url' => $request->url(), 'headers' => $request->headers(), 'data' => $request->data()];
            $headers = ['Content-Type' => 'application/json', 'X-Request-ID' => "probe-request-{$requestNumber}"];

            return match ($requestNumber) {
                1 => Http::response($this->chatPayload($model, 'Synthetic basic result.', 11, 5), 200, $headers),
                2 => Http::response([
                    'id' => 'resp-synthetic-1',
                    'model' => $model,
                    'status' => 'completed',
                    'output' => [[
                        'type' => 'message',
                        'content' => [['type' => 'output_text', 'text' => 'Synthetic responses result.']],
                    ]],
                    'usage' => [
                        'input_tokens' => 12,
                        'output_tokens' => 6,
                        'output_tokens_details' => ['reasoning_tokens' => 2],
                    ],
                ], 200, $headers),
                3 => Http::response([
                    'id' => 'resp-synthetic-2',
                    'model' => $model,
                    'status' => 'completed',
                    'output' => [[
                        'type' => 'message',
                        'content' => [['type' => 'output_text', 'text' => 'Synthetic reconstructed-state result.']],
                    ]],
                    'usage' => ['input_tokens' => 18, 'output_tokens' => 5],
                ], 200, $headers),
                4 => Http::response($this->chatPayload(
                    $model,
                    '{"category":"synthetic","confidence":1,"keywords":["fixture"]}',
                    13,
                    7,
                ), 200, $headers),
                5 => Http::response([
                    'id' => 'chat-tool-1',
                    'model' => $model,
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'synthetic-call-1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'catalog.get_synthetic_good',
                                    'arguments' => '{"sku":"SYN-001"}',
                                ],
                            ]],
                        ],
                        'finish_reason' => 'tool_calls',
                    ]],
                    'usage' => ['prompt_tokens' => 14, 'completion_tokens' => 4],
                ], 200, $headers),
                6 => Http::response($this->chatPayload($model, 'Synthetic tool result accepted.', 15, 5), 200, $headers),
                7 => Http::response($this->chatPayload($model, 'Synthetic store=false result.', 10, 3), 200, $headers),
                default => Http::response(['error' => ['message' => 'unexpected']], 500, $headers),
            };
        });

        $this->artisan('ai:provider-probe', [
            'provider' => 'timeweb',
            '--route' => 'external_sanitized',
            '--profile' => 'all',
            '--model' => $model,
            '--confirm-synthetic' => true,
            '--record-evidence' => true,
            '--operator-reference' => 'test-owner',
        ])->assertSuccessful();

        $this->assertSame(7, $requestNumber);
        $this->assertDatabaseHas('ai_provider_models', [
            'provider_route' => 'external_sanitized',
            'model_id' => $model,
            'endpoint_profile' => 'responses',
        ]);
        $this->assertDatabaseHas('ai_provider_capabilities', [
            'model_id' => $model,
            'capability' => 'strict_structured_outputs',
            'support_state' => 'supported',
            'status' => 'synthetic_tested',
        ]);
        $this->assertDatabaseHas('ai_provider_capabilities', [
            'model_id' => $model,
            'capability' => 'function_calling',
            'support_state' => 'supported',
        ]);
        $this->assertDatabaseHas('ai_provider_capabilities', [
            'model_id' => $model,
            'capability' => 'hosted_web_search',
            'support_state' => 'unsupported',
            'status' => 'documented',
        ]);

        foreach ($sent as $index => $request) {
            $this->assertStringStartsWith('https://api.timeweb.ai/v1/', $request['url']);
            $this->assertSame(['Bearer stage05-external-route-fixture'], $request['headers']['Authorization']);
            $this->assertFalse($request['data']['store']);
            $this->assertArrayNotHasKey('previous_response_id', $request['data']);
            $encoded = json_encode($request['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('stage05-external-route-fixture', $encoded);

            foreach (TimewebDlpCanaries::all()['cross_lane'] as $key => $marker) {
                if ($key !== 'fixture_id') {
                    $this->assertStringNotContainsString($marker, $encoded);
                }
            }

            if ($index === 5) {
                $this->assertContains('tool', array_column($request['data']['messages'], 'role'));
                $this->assertContains('assistant', array_column($request['data']['messages'], 'role'));
            }
        }

        $persisted = json_encode(AiProviderCapability::query()->where('provider_code', 'timeweb')->get()->toArray());
        $this->assertStringNotContainsString('Synthetic basic result.', $persisted);
        $this->assertStringNotContainsString('Synthetic responses result.', $persisted);
        $this->assertStringNotContainsString('stage05-external-route-fixture', $persisted);
        $this->assertTrue(AiProviderCapability::query()->whereNotNull('result_hash')->get()->every(
            fn (AiProviderCapability $capability): bool => preg_match('/^[a-f0-9]{64}$/', $capability->result_hash) === 1,
        ));
        foreach ($queries as $query) {
            foreach (['units', 'entities', 'goods', 'sales', 'purchases', 'emails', 'telephones', 'users'] as $table) {
                $this->assertDoesNotMatchRegularExpression('/(?:from|join|update|into)\s+["`]?'.preg_quote($table, '/').'["`]?\b/', $query);
            }
        }
    }

    public function test_local_probe_requires_current_human_residency_and_inventory(): void
    {
        $model = 'local/synthetic-model';
        $this->inventory(AiProviderRoute::LocalRu, $model);
        $this->pricing(AiProviderRoute::LocalRu, $model);
        Http::fake();

        $this->artisan('ai:provider-probe', [
            'provider' => 'timeweb',
            '--route' => 'local_ru',
            '--profile' => 'basic',
            '--model' => $model,
            '--confirm-synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('residency_unverified');
        Http::assertNothingSent();

        $verification = $this->residency($model);
        $verification->update(['expires_at' => now()->subMinute()]);
        $this->artisan('ai:provider-probe', [
            'provider' => 'timeweb',
            '--route' => 'local_ru',
            '--profile' => 'basic',
            '--model' => $model,
            '--confirm-synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('residency_unverified');
        Http::assertNothingSent();

        AiProviderModel::query()->where('model_id', $model)->update(['active_in_inventory' => false]);
        $verification->update(['expires_at' => now()->addDay()]);
        $this->artisan('ai:provider-probe', [
            'provider' => 'timeweb',
            '--route' => 'local_ru',
            '--profile' => 'basic',
            '--model' => $model,
            '--confirm-synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('timeweb_model_inventory_missing');
        Http::assertNothingSent();
    }

    public function test_dlp_canaries_and_non_synthetic_provider_request_block_before_http(): void
    {
        Http::fake();
        Log::spy();
        $guard = app(TimewebSyntheticDlpGuard::class);
        $fixtures = app(TimewebSyntheticFixtureRegistry::class);

        foreach (TimewebDlpCanaries::all() as $code => $data) {
            $request = $this->manualRequest(
                AiProcessingContour::ExternalSanitized,
                $data,
                hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                true,
            );

            try {
                $guard->authorize($request);
                $this->fail("Canary {$code} must be blocked.");
            } catch (PolicyViolation $violation) {
                $this->assertContains($violation->errorCode, ['timeweb_fixture_hash_blocked', 'timeweb_dlp_blocked']);
            }
        }

        $safe = app(TimewebSyntheticRequestFactory::class)->make(
            AiProviderRoute::LocalRu,
            AiModelProfile::StandardResearch,
            'local_sensitive',
            ['chat_completions'],
            100,
            50,
        );
        $this->assertSame('local_sensitive', $guard->authorize($safe));

        $externalSafe = app(TimewebSyntheticRequestFactory::class)->make(
            AiProviderRoute::ExternalSanitized,
            AiModelProfile::StandardResearch,
            'public_basic',
            ['chat_completions'],
            100,
            50,
        );
        $tamperedItems = $externalSafe->inputItems;
        $tamperedItems[1] = new AiProviderInputItem('sanitized_data', 'public_basic', [
            'fixture_id' => 'SYNTHETIC-COMPANY-001',
            'company_name' => 'Payload substitution must be blocked',
            'description' => 'This content is not the repository-owned fixture.',
        ]);

        foreach ([
            $this->withSyntheticItems($externalSafe, $tamperedItems, $externalSafe->sanitizedPayloadHash),
            $this->withSyntheticItems(
                $externalSafe,
                [...$externalSafe->inputItems, new AiProviderInputItem('sanitized_data', 'arbitrary_extra', ['value' => 'blocked'])],
                $fixtures->requestPayloadHash([
                    ...$externalSafe->inputItems,
                    new AiProviderInputItem('sanitized_data', 'arbitrary_extra', ['value' => 'blocked']),
                ]),
            ),
        ] as $tampered) {
            try {
                $guard->authorize($tampered);
                $this->fail('Changed or arbitrary synthetic payload items must be blocked.');
            } catch (PolicyViolation $violation) {
                $this->assertSame('timeweb_fixture_hash_blocked', $violation->errorCode);
            }
        }

        $domainRequest = $this->manualRequest(
            AiProcessingContour::ExternalSanitized,
            $fixtures->data('public_basic'),
            $fixtures->hash('public_basic'),
            false,
        );

        try {
            app(TimewebExternalSanitizedProvider::class)->createResponse($domainRequest);
            $this->fail('A non-synthetic Unit runtime request must never reach Timeweb in Stage 05.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_domain_runtime_blocked', $violation->errorCode);
        }

        Http::assertNothingSent();

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
    }

    public function test_local_provider_outage_never_uses_external_key_or_route(): void
    {
        $model = 'local/synthetic-model';
        $this->inventory(AiProviderRoute::LocalRu, $model);
        $this->pricing(AiProviderRoute::LocalRu, $model);
        $this->residency($model);
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response([
            'error' => ['message' => 'synthetic outage'],
        ], 503, ['Content-Type' => 'application/json'])]);

        $this->artisan('ai:provider-probe', [
            'provider' => 'timeweb',
            '--route' => 'local_ru',
            '--profile' => 'basic',
            '--model' => $model,
            '--confirm-synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('timeweb_server_error');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame(['Bearer stage05-local-route-fixture'], $request->header('Authorization'));
            $this->assertSame(['local_ru'], $request->header('X-AI-Processing-Route'));
            $this->assertStringNotContainsString('external/synthetic', $request->body());

            return true;
        });
    }

    private function chatPayload(string $model, string $content, int $input, int $output): array
    {
        return [
            'id' => 'chat-synthetic',
            'model' => $model,
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => $input, 'completion_tokens' => $output],
        ];
    }

    private function manualRequest(
        AiProcessingContour $contour,
        array $data,
        string $payloadHash,
        bool $synthetic,
    ): AiProviderRequest {
        return new AiProviderRequest(
            '05000000-0000-4000-8000-000000000009',
            1,
            $contour,
            AiModelProfile::StandardResearch,
            [new AiProviderInputItem('sanitized_data', 'synthetic', $data)],
            ['type' => 'object'],
            [],
            new AiRequestRequirements(['chat_completions'], 100, 50, true),
            hash('sha256', 'idempotency'),
            hash('sha256', 'policy'),
            hash('sha256', 'prompt'),
            hash('sha256', 'schema'),
            $payloadHash,
            ['public' => count($data)],
            false,
            5,
            $synthetic,
        );
    }

    private function withSyntheticItems(AiProviderRequest $request, array $items, string $payloadHash): AiProviderRequest
    {
        return new AiProviderRequest(
            $request->runPublicId,
            $request->stepSequence,
            $request->contour,
            $request->modelProfile,
            $items,
            $request->responseSchema,
            $request->toolSchemas,
            $request->requirements,
            $request->idempotencyKey,
            $request->policyDecisionHash,
            $request->promptHash,
            $request->schemaHash,
            $payloadHash,
            $request->classificationSummary,
            $request->containsLocalOnlyData,
            $request->timeoutSeconds,
            true,
        );
    }
}
