<?php

namespace Tests\Feature\AiSales;

use App\Models\Good;
use App\Models\Product;
use App\Models\UnitBusinessContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Stage08RMigrationRollbackTest extends Stage08TestCase
{
    public function test_stage08r_migrations_are_additive_reversible_and_preserve_stage08_rows(): void
    {
        $actor = $this->prospectingUser();
        $unit = $this->unit(['name' => 'Historical Stage 08R rollback Unit']);
        $context = UnitBusinessContext::query()->findOrFail(
            $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer'])['id'],
        );
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Historical rollback Product',
            'is_published' => true,
        ]);
        $good = Good::query()->create(['name' => 'Historical rollback Good', 'is_published' => true]);
        $good->products()->attach($product->id);
        $jobId = DB::table('prospecting_search_jobs')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'created_by' => $actor->id,
            'owner_user_id' => $actor->id,
            'purpose' => 'buyer_discovery',
            'lane' => 'sales',
            'default_role_code' => 'prospective_customer',
            'primary_good_id' => $good->id,
            'safe_objective' => 'Historical Stage 08 migration fixture.',
            'schema_hash' => hash('sha256', 'stage08r-rollback-job'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('prospecting_search_job_goods')->insert([
            'prospecting_search_job_id' => $jobId,
            'good_id' => $good->id,
            'role' => 'primary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $goodMatchId = DB::table('unit_good_matches')->insertGetId([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'good_id' => $good->id,
            'match_type' => 'potential_need',
            'relevance' => 50,
            'safe_rationale' => 'Historical Stage 08 Good-first row.',
            'evidence_hash' => hash('sha256', 'stage08r-rollback-good-match'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $create = require database_path('migrations/2026_08_16_213000_create_product_first_prospecting_tables.php');
        $extend = require database_path('migrations/2026_08_16_214000_link_good_offer_fits_to_product_matches.php');

        try {
            $extend->down();
            $create->down();

            $this->assertFalse(Schema::hasTable('prospecting_search_job_products'));
            $this->assertFalse(Schema::hasTable('prospecting_candidate_products'));
            $this->assertFalse(Schema::hasTable('unit_product_matches'));
            $this->assertFalse(Schema::hasColumn('prospecting_search_jobs', 'product_mapping_state'));
            $this->assertFalse(Schema::hasColumn('prospecting_search_job_goods', 'compatibility_state'));
            $this->assertFalse(Schema::hasColumn('unit_good_matches', 'unit_product_match_id'));
            $this->assertTrue(Schema::hasTable('prospecting_search_jobs'));
            $this->assertTrue(Schema::hasTable('prospecting_search_job_goods'));
            $this->assertTrue(Schema::hasTable('unit_good_matches'));
            $this->assertSame($good->id, DB::table('prospecting_search_jobs')->where('id', $jobId)->value('primary_good_id'));
            $this->assertSame('primary', DB::table('prospecting_search_job_goods')->where('prospecting_search_job_id', $jobId)->value('role'));
            $this->assertSame($good->id, DB::table('unit_good_matches')->where('id', $goodMatchId)->value('good_id'));
            $this->assertDatabaseHas('units', ['id' => $unit->id]);
            $this->assertDatabaseHas('products', ['id' => $product->id]);
            $this->assertDatabaseHas('goods', ['id' => $good->id]);
        } finally {
            if (! Schema::hasTable('prospecting_search_job_products')) {
                $create->up();
            }
            if (! Schema::hasColumn('unit_good_matches', 'unit_product_match_id')) {
                $extend->up();
            }
        }

        $this->assertTrue(Schema::hasTable('unit_product_matches'));
        $this->assertTrue(Schema::hasColumn('unit_good_matches', 'unit_product_match_id'));
        $this->assertDatabaseHas('prospecting_search_jobs', [
            'id' => $jobId,
            'product_mapping_state' => 'legacy_unreconciled',
        ]);
        $this->assertDatabaseHas('unit_good_matches', [
            'id' => $goodMatchId,
            'compatibility_state' => 'legacy_unreconciled',
        ]);
    }
}
