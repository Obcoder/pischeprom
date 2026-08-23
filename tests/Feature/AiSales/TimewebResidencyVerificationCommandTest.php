<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Infrastructure\AiSales\Timeweb\TimewebResidencyVerificationService;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class TimewebResidencyVerificationCommandTest extends Stage05TestCase
{
    public function test_human_verification_requires_permission_exact_inventory_and_explicit_confirmation(): void
    {
        $model = 'local/synthetic-model';
        $this->inventory(AiProviderRoute::LocalRu, $model);
        $verifier = User::factory()->create(['status' => 'active']);
        Permission::query()->firstOrCreate([
            'name' => TimewebResidencyVerificationService::PERMISSION,
            'guard_name' => 'crm',
        ]);
        $verifier->givePermissionTo(TimewebResidencyVerificationService::PERMISSION);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $arguments = [
            '--model' => $model,
            '--verifier-id' => $verifier->id,
            '--evidence-reference' => 'panel-review:local-filter-2026-08-15',
            '--evidence-hash' => hash('sha256', 'synthetic-human-reviewed-evidence'),
        ];

        $this->artisan('ai:timeweb-residency:verify', $arguments)->assertExitCode(2);
        $this->assertDatabaseCount('ai_model_residency_verifications', 0);

        $arguments['--confirm-human-reviewed'] = true;
        $unsafe = $arguments;
        $unsafe['--evidence-reference'] = 'panel-review:stage05-local-route-fixture';
        $this->artisan('ai:timeweb-residency:verify', $unsafe)
            ->assertFailed()
            ->expectsOutputToContain('timeweb_residency_evidence_invalid');
        $this->assertDatabaseCount('ai_model_residency_verifications', 0);

        $this->artisan('ai:timeweb-residency:verify', $arguments)->assertSuccessful();
        $this->assertDatabaseHas('ai_model_residency_verifications', [
            'provider_code' => 'timeweb',
            'provider_route' => 'local_ru',
            'model_id' => $model,
            'declared_country' => 'RU',
            'status' => 'verified',
            'verified_by' => $verifier->id,
        ]);
        $verification = \App\Models\AiModelResidencyVerification::query()->firstOrFail();
        $this->assertTrue($verification->expires_at->isFuture());
        $this->assertLessThanOrEqual(30, now()->diffInDays($verification->expires_at, false));
        Http::assertNothingSent();
    }

    public function test_model_name_never_creates_residency_without_authorized_human_evidence(): void
    {
        $model = 'local/obviously-russian-name';
        config()->set('ai-sales.providers.timeweb.routes.local_ru.model_ids', [$model]);
        $this->inventory(AiProviderRoute::LocalRu, $model);
        $unauthorized = User::factory()->create(['status' => 'active']);

        $this->artisan('ai:timeweb-residency:verify', [
            '--model' => $model,
            '--verifier-id' => $unauthorized->id,
            '--evidence-reference' => 'panel-review:claimed-local',
            '--evidence-hash' => hash('sha256', 'claimed-local'),
            '--confirm-human-reviewed' => true,
        ])->assertFailed()->expectsOutputToContain('timeweb_residency_verifier_forbidden');

        $this->assertDatabaseCount('ai_model_residency_verifications', 0);
        Http::assertNothingSent();
    }
}
