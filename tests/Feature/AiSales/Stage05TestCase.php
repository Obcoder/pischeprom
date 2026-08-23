<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Models\AiModelResidencyVerification;
use App\Models\AiProviderModel;
use App\Models\AiProviderPricingSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class Stage05TestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set([
            'app.key' => 'base64:stage05-test-application-key',
            'ai-sales.enabled' => true,
            'ai-sales.external_calls_enabled' => true,
            'ai-sales.local_ru_calls_enabled' => true,
            'ai-sales.external_sanitized_calls_enabled' => true,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.transport_mode' => 'timeweb_synthetic_only',
            'ai-sales.providers.timeweb.enabled' => true,
            'ai-sales.providers.timeweb.base_url' => 'https://api.timeweb.ai/v1',
            'ai-sales.providers.timeweb.connect_timeout_seconds' => 3,
            'ai-sales.providers.timeweb.timeout_seconds' => 10,
            'ai-sales.providers.timeweb.max_response_bytes' => 1_048_576,
            'ai-sales.providers.timeweb.routes.local_ru.enabled' => true,
            'ai-sales.providers.timeweb.routes.local_ru.api_key' => 'stage05-local-route-fixture',
            'ai-sales.providers.timeweb.routes.local_ru.model_ids' => ['local/synthetic-model'],
            'ai-sales.providers.timeweb.routes.external_sanitized.enabled' => true,
            'ai-sales.providers.timeweb.routes.external_sanitized.api_key' => 'stage05-external-route-fixture',
            'ai-sales.providers.timeweb.routes.external_sanitized.models' => [
                'luna' => 'external/synthetic-luna',
                'terra' => 'external/synthetic-terra',
                'sol' => 'external/synthetic-sol',
            ],
            'ai-sales.providers.timeweb.probe.enabled' => true,
            'ai-sales.providers.timeweb.probe.synthetic_only' => true,
            'ai-sales.providers.timeweb.probe.max_rub' => '10.0000',
            'ai-sales.providers.timeweb.probe.max_input_tokens' => 20_000,
            'ai-sales.providers.timeweb.probe.max_output_tokens' => 5_000,
            'ai-sales.providers.timeweb.probe.max_requests' => 20,
            'ai-sales.providers.timeweb.probe.max_wall_clock_seconds' => 120,
            'ai-sales.providers.timeweb.probe.pricing_snapshot_version' => 'test-2026-08-15',
        ]);
    }

    protected function inventory(
        AiProviderRoute $route,
        string $modelId,
        AiProviderEndpointProfile $endpoint = AiProviderEndpointProfile::ChatCompletions,
    ): AiProviderModel {
        return AiProviderModel::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => $route->value,
            'model_id' => $modelId,
            'display_label' => $modelId,
            'endpoint_profile' => $endpoint,
            'active_in_inventory' => true,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'safe_metadata' => ['object' => 'model'],
            'source_reference' => 'http-fake:/v1/models',
            'metadata_hash' => hash('sha256', $modelId),
            'created_by_reference' => 'test-suite',
            'updated_by_reference' => 'test-suite',
        ]);
    }

    protected function pricing(AiProviderRoute $route, string $modelId): AiProviderPricingSnapshot
    {
        return AiProviderPricingSnapshot::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => $route->value,
            'model_id' => $modelId,
            'version' => 'test-2026-08-15',
            'currency' => 'RUB',
            'input_per_million' => '1.000000',
            'output_per_million' => '2.000000',
            'reasoning_per_million' => '2.000000',
            'effective_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'source_reference' => 'http-fake:pricing',
            'source_hash' => hash('sha256', $route->value.':'.$modelId.':pricing'),
            'recorded_by_reference' => 'test-suite',
        ]);
    }

    protected function residency(string $modelId): AiModelResidencyVerification
    {
        $user = User::factory()->create(['status' => 'active']);

        return AiModelResidencyVerification::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => 'local_ru',
            'model_id' => $modelId,
            'declared_contour' => 'local_ru',
            'declared_country' => 'RU',
            'evidence_reference' => 'human-test:local-panel-filter',
            'evidence_hash' => hash('sha256', 'human-test:'.$modelId),
            'verified_by' => $user->id,
            'verified_at' => now(),
            'expires_at' => now()->addDay(),
            'status' => 'verified',
            'probe_version' => 'stage05-test-v1',
        ]);
    }
}
