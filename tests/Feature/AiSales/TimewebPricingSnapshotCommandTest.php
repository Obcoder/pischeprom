<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Infrastructure\AiSales\Timeweb\TimewebPricingSnapshotService;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeCostEstimator;
use App\Models\AiProviderPricingSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class TimewebPricingSnapshotCommandTest extends Stage05TestCase
{
    public function test_authorized_human_records_idempotent_immutable_exact_model_pricing(): void
    {
        $model = 'external/synthetic-terra';
        $this->inventory(AiProviderRoute::ExternalSanitized, $model);
        $verifier = User::factory()->create(['status' => 'active']);
        Permission::query()->firstOrCreate([
            'name' => TimewebPricingSnapshotService::PERMISSION,
            'guard_name' => 'crm',
        ]);
        $verifier->givePermissionTo(TimewebPricingSnapshotService::PERMISSION);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $arguments = [
            '--route' => 'external_sanitized',
            '--model' => $model,
            '--verifier-id' => $verifier->id,
            '--input-per-million' => '1.25',
            '--output-per-million' => '2.50',
            '--reasoning-per-million' => '3.75',
            '--source-reference' => 'public-doc:timeweb-pricing-2026-08-15',
            '--source-hash' => hash('sha256', 'synthetic-reviewed-pricing-evidence'),
            '--confirm-human-reviewed' => true,
        ];

        $this->artisan('ai:timeweb-pricing:record', $arguments)->assertSuccessful();
        $this->artisan('ai:timeweb-pricing:record', $arguments)->assertSuccessful();
        $this->assertDatabaseCount('ai_provider_pricing_snapshots', 1);
        $this->assertDatabaseHas('ai_provider_pricing_snapshots', [
            'provider_code' => 'timeweb',
            'provider_route' => 'external_sanitized',
            'model_id' => $model,
            'version' => 'test-2026-08-15',
            'currency' => 'RUB',
            'input_per_million' => '1.250000',
            'output_per_million' => '2.500000',
        ]);

        $snapshot = AiProviderPricingSnapshot::query()->firstOrFail();
        $estimate = app(TimewebProbeCostEstimator::class)->actualOrReserved(
            AiProviderRoute::ExternalSanitized,
            $model,
            new AiProviderUsage(100_000, 20_000, 5_000),
            '99.0000',
        );
        $this->assertSame('0.1813', $estimate);
        $this->assertSame(
            '0.2000',
            app(TimewebProbeCostEstimator::class)->maximum(
                AiProviderRoute::ExternalSanitized,
                $model,
                100_000,
                20_000,
            ),
        );

        try {
            $snapshot->update(['input_per_million' => '9.000000']);
            $this->fail('Pricing snapshot must be immutable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_pricing_requires_dedicated_permission_and_same_version_cannot_be_changed(): void
    {
        $model = 'external/synthetic-terra';
        $this->inventory(AiProviderRoute::ExternalSanitized, $model);
        $unauthorized = User::factory()->create(['status' => 'active']);
        $base = [
            '--route' => 'external_sanitized',
            '--model' => $model,
            '--verifier-id' => $unauthorized->id,
            '--input-per-million' => '1.000000',
            '--output-per-million' => '2.000000',
            '--source-reference' => 'public-doc:timeweb-pricing',
            '--source-hash' => hash('sha256', 'pricing-v1'),
            '--confirm-human-reviewed' => true,
        ];

        $this->artisan('ai:timeweb-pricing:record', $base)
            ->assertFailed()
            ->expectsOutputToContain('timeweb_pricing_verifier_forbidden');
        $this->assertDatabaseCount('ai_provider_pricing_snapshots', 0);

        Permission::query()->firstOrCreate([
            'name' => TimewebPricingSnapshotService::PERMISSION,
            'guard_name' => 'crm',
        ]);
        $unauthorized->givePermissionTo(TimewebPricingSnapshotService::PERMISSION);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $unsafe = $base;
        $unsafe['--source-reference'] = 'public-doc:stage05-external-route-fixture';
        $this->artisan('ai:timeweb-pricing:record', $unsafe)
            ->assertFailed()
            ->expectsOutputToContain('timeweb_pricing_evidence_invalid');
        $this->assertDatabaseCount('ai_provider_pricing_snapshots', 0);

        $this->artisan('ai:timeweb-pricing:record', $base)->assertSuccessful();
        $base['--input-per-million'] = '9.000000';
        $this->artisan('ai:timeweb-pricing:record', $base)
            ->assertFailed()
            ->expectsOutputToContain('timeweb_pricing_version_conflict');
        $this->assertDatabaseCount('ai_provider_pricing_snapshots', 1);
        Http::assertNothingSent();
    }
}
