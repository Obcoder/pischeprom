<?php

namespace Tests\Feature\AiSales;

use App\Models\AiProviderModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TimewebModelInventoryCommandTest extends Stage05TestCase
{
    public function test_dry_run_normalizes_inventory_without_writes_or_domain_queries(): void
    {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = mb_strtolower($query->sql);
        });
        Http::fake(['https://api.timeweb.ai/v1/models' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'openai/synthetic-b', 'object' => 'model', 'owned_by' => 'synthetic-owner', 'created' => 123, 'raw_secret' => 'discard-me'],
                ['id' => 'openai/synthetic-a', 'object' => 'model'],
            ],
            'raw_top_level' => 'discard-me-too',
        ], 200, ['Content-Type' => 'application/json', 'X-Request-ID' => 'inventory-safe-1'])]);

        $this->artisan('ai:timeweb-models:sync', [
            '--route' => 'external_sanitized',
            '--dry-run' => true,
            '--synthetic' => true,
        ])->assertSuccessful()->expectsOutputToContain('Dry-run only');

        $this->assertDatabaseCount('ai_provider_models', 0);
        $this->assertDatabaseCount('ai_model_residency_verifications', 0);
        $forbiddenTables = ['units', 'entities', 'goods', 'sales', 'purchases', 'emails', 'telephones', 'users'];

        foreach ($queries as $query) {
            foreach ($forbiddenTables as $table) {
                $this->assertDoesNotMatchRegularExpression('/(?:from|join|update|into)\s+["`]?'.preg_quote($table, '/').'["`]?\b/', $query);
            }
        }

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'Authorization',
            'Bearer stage05-external-route-fixture',
        ));
    }

    public function test_apply_is_explicit_idempotent_and_marks_missing_models_inactive_without_deletion(): void
    {
        Http::fake(['https://api.timeweb.ai/v1/models' => Http::sequence()
            ->push([
                'data' => [
                    ['id' => 'external/synthetic-luna', 'object' => 'model', 'owned_by' => 'api-key-must-drop'],
                    ['id' => 'external/synthetic-terra', 'object' => 'model'],
                ],
            ], 200, ['Content-Type' => 'application/json'])
            ->push([
                'data' => [
                    ['id' => 'external/synthetic-terra', 'object' => 'model'],
                ],
            ], 200, ['Content-Type' => 'application/json'])]);

        $arguments = [
            '--route' => 'external_sanitized',
            '--apply' => true,
            '--confirm-apply' => true,
            '--synthetic' => true,
            '--operator-reference' => 'test-operator',
        ];
        $this->artisan('ai:timeweb-models:sync', $arguments)->assertSuccessful();
        $this->assertDatabaseCount('ai_provider_models', 2);
        $this->assertDatabaseHas('ai_provider_models', [
            'model_id' => 'external/synthetic-luna',
            'active_in_inventory' => true,
            'endpoint_profile' => 'unsupported',
        ]);
        $this->assertDatabaseCount('ai_model_residency_verifications', 0);
        $this->assertSame(
            ['object' => 'model'],
            AiProviderModel::query()->where('model_id', 'external/synthetic-luna')->value('safe_metadata'),
        );

        $this->artisan('ai:timeweb-models:sync', $arguments)->assertSuccessful();
        $this->assertDatabaseCount('ai_provider_models', 2);
        $this->assertDatabaseHas('ai_provider_models', [
            'model_id' => 'external/synthetic-luna',
            'active_in_inventory' => false,
        ]);
        $this->assertDatabaseHas('ai_provider_models', [
            'model_id' => 'external/synthetic-terra',
            'active_in_inventory' => true,
        ]);
        $this->assertSame(
            ['object' => 'model'],
            AiProviderModel::query()->where('model_id', 'external/synthetic-terra')->value('safe_metadata'),
        );
    }

    public function test_apply_without_confirmation_and_production_environment_block_before_http(): void
    {
        Http::fake();
        $this->artisan('ai:timeweb-models:sync', [
            '--route' => 'local_ru',
            '--apply' => true,
            '--synthetic' => true,
        ])->assertExitCode(2);
        Http::assertNothingSent();

        app()->detectEnvironment(fn (): string => 'production');
        $this->artisan('ai:timeweb-models:sync', [
            '--route' => 'local_ru',
            '--dry-run' => true,
            '--synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('timeweb_environment_blocked');
        Http::assertNothingSent();
    }

    public function test_credential_shaped_model_identifier_is_rejected_without_persistence(): void
    {
        Http::fake(['https://api.timeweb.ai/v1/models' => Http::response([
            'data' => [['id' => 'stage05-external-route-fixture', 'object' => 'model']],
        ], 200, ['Content-Type' => 'application/json'])]);

        $this->artisan('ai:timeweb-models:sync', [
            '--route' => 'external_sanitized',
            '--dry-run' => true,
            '--synthetic' => true,
        ])->assertFailed()->expectsOutputToContain('timeweb_model_id_invalid');

        $this->assertDatabaseCount('ai_provider_models', 0);
    }
}
