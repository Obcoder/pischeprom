<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Stage05MigrationRollbackTest extends Stage05TestCase
{
    public function test_stage05_migration_rolls_back_only_additive_inventory_structures(): void
    {
        $historicalCapabilityId = DB::table('ai_provider_capabilities')->insertGetId([
            'provider_code' => 'fake',
            'provider_route' => 'external_sanitized',
            'model_id' => 'historical-fake-model',
            'contour' => 'external_sanitized',
            'capability' => 'chat_completions',
            'status' => 'synthetic_tested',
            'support_state' => 'supported',
            'evidence_hash' => hash('sha256', 'historical-capability'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unverifiedCapabilityId = DB::table('ai_provider_capabilities')->insertGetId([
            'provider_code' => 'future-provider',
            'provider_route' => 'external_sanitized',
            'model_id' => 'unverified-model',
            'contour' => 'external_sanitized',
            'capability' => 'chat_completions',
            'status' => 'unknown',
            'support_state' => 'unsupported',
            'evidence_hash' => hash('sha256', 'unverified-capability'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_provider_models')->insert([
            'provider_code' => 'timeweb',
            'provider_route' => 'external_sanitized',
            'model_id' => 'synthetic/model',
            'endpoint_profile' => 'unsupported',
            'active_in_inventory' => true,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'source_reference' => 'test',
            'metadata_hash' => hash('sha256', 'synthetic/model'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_08_15_193000_create_timeweb_ai_gateway_inventory_tables.php');

        try {
            $migration->down();

            $this->assertFalse(Schema::hasTable('ai_provider_models'));
            $this->assertFalse(Schema::hasTable('ai_provider_pricing_snapshots'));
            $this->assertTrue(Schema::hasTable('ai_provider_capabilities'));
            $this->assertFalse(Schema::hasColumn('ai_provider_capabilities', 'support_state'));
            $this->assertTrue(Schema::hasTable('ai_model_residency_verifications'));
            $this->assertTrue(DB::table('ai_provider_capabilities')->where('id', $historicalCapabilityId)->exists());
        } finally {
            $migration->up();
        }

        $this->assertTrue(Schema::hasTable('ai_provider_models'));
        $this->assertTrue(Schema::hasColumn('ai_provider_capabilities', 'support_state'));
        $this->assertSame(
            'supported',
            DB::table('ai_provider_capabilities')->where('id', $historicalCapabilityId)->value('support_state'),
        );
        $this->assertSame(
            'unknown',
            DB::table('ai_provider_capabilities')->where('id', $unverifiedCapabilityId)->value('support_state'),
        );
    }
}
