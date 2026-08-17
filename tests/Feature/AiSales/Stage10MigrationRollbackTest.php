<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage10MigrationRollbackTest extends Stage10TestCase
{
    public function test_stage10_migration_is_additive_reversible_and_reapplies_on_isolated_sqlite(): void
    {
        $migration = require database_path('migrations/2026_08_17_100000_create_explainable_prospecting_score_tables.php');
        $tables = [
            'unit_product_relevance_snapshots', 'unit_product_relevance_factors',
            'unit_good_fit_snapshots', 'unit_good_fit_factors',
            'unit_prospect_priority_snapshots', 'unit_prospect_priority_factors',
        ];
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        try {
            $migration->down();
            foreach ($tables as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
            $this->assertTrue(Schema::hasTable('unit_product_matches'));
            $this->assertTrue(Schema::hasTable('unit_good_matches'));
            $this->assertTrue(Schema::hasTable('unit_business_contexts'));
        } finally {
            if (! Schema::hasTable('unit_product_relevance_snapshots')) {
                $migration->up();
            }
        }
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }
}
