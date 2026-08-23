<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage09MigrationRollbackTest extends Stage09TestCase
{
    public function test_stage09_migration_is_additive_reversible_and_preserves_stage08r_rows(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $migration = require database_path('migrations/2026_08_17_090000_create_product_first_search_discovery_tables.php');

        try {
            $migration->down();
            $this->assertFalse(Schema::hasTable('prospecting_search_executions'));
            $this->assertFalse(Schema::hasTable('prospecting_search_results'));
            $this->assertFalse(Schema::hasTable('prospecting_search_usage_records'));
            $this->assertFalse(Schema::hasTable('prospecting_public_fetches'));
            $this->assertFalse(Schema::hasTable('prospecting_public_research_records'));
            $this->assertFalse(Schema::hasColumn('prospecting_search_queries', 'plan_hash'));
            $this->assertTrue(Schema::hasTable('prospecting_search_jobs'));
            $this->assertTrue(Schema::hasTable('prospecting_search_job_products'));
            $this->assertDatabaseHas('prospecting_search_jobs', ['id' => $job->id]);
        } finally {
            if (! Schema::hasTable('prospecting_search_executions')) {
                $migration->up();
            }
        }

        $this->assertTrue(Schema::hasColumn('prospecting_search_queries', 'plan_hash'));
        $this->assertTrue(Schema::hasTable('prospecting_public_research_records'));
        $this->assertDatabaseHas('prospecting_search_jobs', ['id' => $job->id]);
    }
}
