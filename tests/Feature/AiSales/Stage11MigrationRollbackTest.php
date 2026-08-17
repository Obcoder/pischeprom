<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage11MigrationRollbackTest extends Stage11TestCase
{
    public function test_stage11_metadata_migration_is_additive_reversible_and_reapplies_on_isolated_sqlite(): void
    {
        $migration = require database_path('migrations/2026_08_17_110000_add_find_buyers_launch_metadata_to_prospecting_search_jobs.php');
        $columns = [
            'launch_source_type', 'launch_source_id', 'wizard_version', 'disclosure_policy_hash',
            'draft_idempotency_key_hash', 'submitted_by', 'submitted_at',
        ];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('prospecting_search_jobs', $column));
        }

        try {
            $migration->down();
            foreach ($columns as $column) {
                $this->assertFalse(Schema::hasColumn('prospecting_search_jobs', $column));
            }
            $this->assertTrue(Schema::hasTable('prospecting_search_jobs'));
            $this->assertTrue(Schema::hasTable('prospecting_search_job_products'));
            $this->assertTrue(Schema::hasTable('prospecting_search_queries'));
        } finally {
            if (! Schema::hasColumn('prospecting_search_jobs', 'wizard_version')) {
                $migration->up();
            }
        }

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('prospecting_search_jobs', $column));
        }
    }
}
