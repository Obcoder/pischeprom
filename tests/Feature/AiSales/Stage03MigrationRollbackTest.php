<?php

namespace Tests\Feature\AiSales;

use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Stage03MigrationRollbackTest extends UnitContextsTestCase
{
    public function test_stage03_rollback_drops_only_new_structures_and_preserves_historical_data(): void
    {
        $unit = $this->unit(['name' => 'Historical Unit']);
        $entity = Entity::query()->create(['name' => 'Historical Entity']);
        $unit->entities()->attach($entity->id);
        $migrationFiles = [
            database_path('migrations/2026_08_15_180000_create_unit_market_roles_and_contexts_tables.php'),
            database_path('migrations/2026_08_15_181000_create_unit_provenance_tables.php'),
            database_path('migrations/2026_08_15_182000_create_entity_candidate_proposals_and_unit_audit_events.php'),
        ];
        $migrations = array_map(static fn (string $file) => require $file, $migrationFiles);

        try {
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }

            $this->assertFalse(Schema::hasTable('unit_business_contexts'));
            $this->assertFalse(Schema::hasTable('unit_observations'));
            $this->assertFalse(Schema::hasTable('entity_candidate_proposals'));
            $this->assertTrue(Schema::hasTable('units'));
            $this->assertTrue(Schema::hasTable('entities'));
            $this->assertTrue(Schema::hasTable('entity_unit'));
            $this->assertSame('Historical Unit', DB::table('units')->where('id', $unit->id)->value('name'));
            $this->assertSame('Historical Entity', DB::table('entities')->where('id', $entity->id)->value('name'));
            $this->assertTrue(DB::table('entity_unit')->where('unit_id', $unit->id)->where('entity_id', $entity->id)->exists());
        } finally {
            foreach ($migrations as $migration) {
                $migration->up();
            }
        }
    }
}
