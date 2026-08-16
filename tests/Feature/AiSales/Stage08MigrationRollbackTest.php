<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage08MigrationRollbackTest extends Stage08TestCase
{
    public function test_stage08_migrations_are_additive_reversible_and_preserve_stage03_tables(): void
    {
        $actor = $this->prospectingUser();
        $unit = $this->unit(['name' => 'Historical Stage 03 Unit']);
        $context = $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $first = require database_path('migrations/2026_08_16_210000_create_prospecting_search_jobs_and_queries.php');
        $second = require database_path('migrations/2026_08_16_211000_create_prospecting_candidates.php');
        $third = require database_path('migrations/2026_08_16_212000_create_unit_good_matches_and_extend_contact_links.php');

        try {
            $third->down();
            $second->down();
            $first->down();
            $this->assertFalse(Schema::hasTable('prospecting_search_jobs'));
            $this->assertFalse(Schema::hasTable('prospecting_candidates'));
            $this->assertFalse(Schema::hasTable('unit_good_matches'));
            $this->assertFalse(Schema::hasColumn('unit_contact_context_links', 'communication_state'));
            $this->assertTrue(Schema::hasTable('units'));
            $this->assertTrue(Schema::hasTable('unit_business_contexts'));
            $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Historical Stage 03 Unit']);
            $this->assertDatabaseHas('unit_business_contexts', ['id' => $context['id'], 'unit_id' => $unit->id]);
        } finally {
            if (! Schema::hasTable('prospecting_search_jobs')) {
                $first->up();
            }
            if (! Schema::hasTable('prospecting_candidates')) {
                $second->up();
            }
            if (! Schema::hasTable('unit_good_matches')) {
                $third->up();
            }
        }
        $this->assertTrue(Schema::hasTable('unit_good_matches'));
        $this->assertTrue(Schema::hasColumn('unit_contact_context_links', 'communication_state'));
    }
}
