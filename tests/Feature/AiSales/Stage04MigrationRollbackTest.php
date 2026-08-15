<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Stage04MigrationRollbackTest extends Stage04TestCase
{
    public function test_stage04_rollback_preserves_shared_usage_ledger_and_stage03_domain(): void
    {
        $unit = $this->unit(['name' => 'Historical Stage 03 Unit']);
        $usageId = DB::table('ai_usage_records')->insertGetId([
            'provider' => 'historical_fake',
            'operation' => 'historical_price_list_operation',
            'status' => 'success',
            'cost_is_estimate' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migrationFiles = [
            database_path('migrations/2026_08_15_190000_create_ai_control_plane_runs_tables.php'),
            database_path('migrations/2026_08_15_191000_create_ai_control_plane_audit_tables.php'),
            database_path('migrations/2026_08_15_192000_create_ai_provider_verification_and_settings_tables.php'),
        ];
        $migrations = array_map(static fn (string $file) => require $file, $migrationFiles);

        try {
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }

            $this->assertFalse(Schema::hasTable('ai_agent_runs'));
            $this->assertFalse(Schema::hasTable('ai_policy_decisions'));
            $this->assertFalse(Schema::hasTable('ai_provider_capabilities'));
            $this->assertTrue(Schema::hasTable('ai_usage_records'));
            $this->assertTrue(Schema::hasTable('unit_business_contexts'));
            $this->assertTrue(Schema::hasColumn('ai_usage_records', 'price_list_import_id'));
            $this->assertFalse(Schema::hasColumn('ai_usage_records', 'ai_agent_run_id'));
            $this->assertSame(
                'historical_price_list_operation',
                DB::table('ai_usage_records')->where('id', $usageId)->value('operation'),
            );
            $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Historical Stage 03 Unit']);
        } finally {
            foreach ($migrations as $migration) {
                $migration->up();
            }
        }

        $this->assertTrue(Schema::hasColumn('ai_usage_records', 'ai_agent_run_id'));
        $this->assertSame(
            'historical_price_list_operation',
            DB::table('ai_usage_records')->where('id', $usageId)->value('operation'),
        );
    }
}
