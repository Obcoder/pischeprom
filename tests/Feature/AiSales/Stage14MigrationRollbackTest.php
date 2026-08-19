<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage14MigrationRollbackTest extends Stage14TestCase
{
    public function test_stage14_migration_is_additive_reversible_and_reapplicable(): void
    {
        $migration = require database_path('migrations/2026_08_19_140000_create_ai_sales_campaign_orchestration_tables.php');
        $tables = ['ai_sales_campaigns', 'ai_sales_campaign_products', 'ai_sales_campaign_run_links'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasTable('ai_agent_runs'));
        $this->assertTrue(Schema::hasTable('prospecting_search_jobs'));
        $this->assertTrue(Schema::hasTable('units'));
        $this->assertTrue(Schema::hasTable('entities'));

        $migration->down();
        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasTable('ai_agent_runs'));
        $this->assertTrue(Schema::hasTable('prospecting_candidates'));
        $this->assertTrue(Schema::hasTable('outreach_drafts'));

        $migration->up();
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }
}
