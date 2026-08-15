<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Contracts\TimewebAiProviderInterface;
use App\Domain\AiSales\Enums\AiCapabilitySupportStatus;
use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Infrastructure\AiSales\Providers\TimewebExternalSanitizedProvider;
use App\Infrastructure\AiSales\Providers\TimewebLocalRuProvider;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeBudgetGuard;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticRequestFactory;
use App\Models\AiProviderCapability;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class TimewebProviderAdapterContractTest extends Stage05TestCase
{
    public function test_route_adapters_share_transport_but_keep_fixed_identity_and_keys(): void
    {
        $local = app(TimewebLocalRuProvider::class);
        $external = app(TimewebExternalSanitizedProvider::class);

        $this->assertInstanceOf(TimewebAiProviderInterface::class, $local);
        $this->assertSame('timeweb', $local->code());
        $this->assertSame(AiProviderRoute::LocalRu, $local->route());
        $this->assertSame(AiProviderRoute::ExternalSanitized, $external->route());
        $this->assertTrue($local->healthCheck()->available);
        $this->assertTrue($external->healthCheck()->available);
        Http::assertNothingSent();
    }

    public function test_external_adapter_maps_allowlisted_synthetic_chat_without_provider_state(): void
    {
        $model = 'external/synthetic-terra';
        $this->inventory(AiProviderRoute::ExternalSanitized, $model, AiProviderEndpointProfile::ChatCompletions);
        $this->pricing(AiProviderRoute::ExternalSanitized, $model);
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response([
            'id' => 'adapter-chat-1',
            'model' => $model,
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Synthetic adapter output.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 8,
                'prompt_tokens_details' => ['cached_tokens' => 3],
                'completion_tokens_details' => ['reasoning_tokens' => 2],
            ],
        ], 200, ['Content-Type' => 'application/json', 'X-Request-ID' => 'adapter-safe-1'])]);
        $request = app(TimewebSyntheticRequestFactory::class)->make(
            AiProviderRoute::ExternalSanitized,
            AiModelProfile::StandardResearch,
            'public_basic',
            ['chat_completions'],
            100,
            50,
        );

        $response = app(TimewebExternalSanitizedProvider::class)->createResponse($request);

        $this->assertSame(AiProviderResponseStatus::Completed, $response->status);
        $this->assertSame('adapter-safe-1', $response->requestId);
        $this->assertSame(20, $response->usage->inputTokens);
        $this->assertSame(8, $response->usage->outputTokens);
        $this->assertSame(2, $response->usage->reasoningTokens);
        $this->assertSame(3, $response->usage->cachedTokens);
        $this->assertNotSame('0.0000', $response->usage->normalizedRubAmount);
        Http::assertSent(function (Request $sent): bool {
            $this->assertSame(['Bearer stage05-external-route-fixture'], $sent->header('Authorization'));
            $this->assertFalse($sent['store']);
            $this->assertArrayNotHasKey('previous_response_id', $sent->data());

            return true;
        });
    }

    public function test_capability_profile_requires_inventory_support_state_and_lifecycle_evidence(): void
    {
        $model = 'external/synthetic-terra';
        $this->inventory(AiProviderRoute::ExternalSanitized, $model);
        AiProviderCapability::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => 'external_sanitized',
            'model_id' => $model,
            'contour' => 'external_sanitized',
            'capability' => 'chat_completions',
            'status' => AiCapabilityVerificationStatus::SyntheticTested,
            'support_state' => AiCapabilitySupportStatus::Supported,
            'max_context_tokens' => 1000,
            'max_output_tokens' => 500,
            'evidence_reference' => 'http-fake:adapter-contract',
            'evidence_hash' => hash('sha256', 'adapter-contract'),
            'verified_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $profile = app(TimewebExternalSanitizedProvider::class)->capabilities(AiModelProfile::StandardResearch);
        $this->assertSame(AiCapabilityVerificationStatus::SyntheticTested, $profile->capabilities['chat_completions']);

        AiProviderCapability::query()->where('model_id', $model)->update(['support_state' => 'unsupported']);
        $blocked = app(TimewebExternalSanitizedProvider::class)->capabilities(AiModelProfile::StandardResearch);
        $this->assertSame(AiCapabilityVerificationStatus::Unknown, $blocked->capabilities['chat_completions']);
    }

    public function test_application_registry_can_select_timeweb_only_in_explicit_synthetic_mode(): void
    {
        config()->set('ai-sales.transport_mode', 'timeweb_synthetic_only');
        app()->forgetInstance(AiProviderRegistry::class);
        $providers = app(AiProviderRegistry::class)->all();

        $this->assertCount(2, $providers);
        $this->assertContainsOnlyInstancesOf(TimewebAiProviderInterface::class, $providers);
        $this->assertSame(
            ['external_sanitized', 'local_ru'],
            collect($providers)->map(fn ($provider) => $provider->route()->value)->sort()->values()->all(),
        );
        Http::assertNothingSent();
    }

    public function test_probe_budget_and_missing_pricing_block_before_next_http_request(): void
    {
        config()->set('ai-sales.providers.timeweb.probe.max_requests', 1);
        $budget = app(TimewebProbeBudgetGuard::class);
        $budget->reserve(100, 50, '0.1000');

        try {
            $budget->reserve(1, 1, '0.0001');
            $this->fail('Second request must exceed the hard request cap.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_probe_budget_exceeded', $violation->errorCode);
        }

        config()->set('ai-sales.providers.timeweb.probe.max_requests', 20);
        $model = 'external/synthetic-terra';
        $this->inventory(AiProviderRoute::ExternalSanitized, $model);
        Http::fake();
        $this->artisan('ai:provider-probe', [
            'provider' => 'timeweb',
            '--route' => 'external_sanitized',
            '--profile' => 'basic',
            '--model' => $model,
            '--confirm-synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('timeweb_pricing_snapshot_unverified');
        Http::assertNothingSent();
    }
}
