<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchType;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Services\UnitGoodMatchService;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\UnitBusinessContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductReconciliationCommandTest extends Stage08TestCase
{
    public function test_reconciliation_is_dry_run_by_default_deterministic_chunked_and_idempotent(): void
    {
        $actor = $this->prospectingUser();
        $exactProduct = $this->product('Exact Product');
        $ambiguousA = $this->product('Ambiguous Product A');
        $ambiguousB = $this->product('Ambiguous Product B');
        $exactGood = $this->good('DO_NOT_PRINT_EXACT_GOOD');
        $missingGood = $this->good('DO_NOT_PRINT_MISSING_GOOD');
        $ambiguousGood = $this->good('DO_NOT_PRINT_AMBIGUOUS_GOOD');
        $exactGood->products()->attach($exactProduct->id);
        $ambiguousGood->products()->attach([$ambiguousA->id, $ambiguousB->id]);

        $exactJob = $this->legacyJob($actor->id, $exactGood->id);
        $missingJob = $this->legacyJob($actor->id, $missingGood->id);
        $ambiguousJob = $this->legacyJob($actor->id, $ambiguousGood->id);
        $candidate = ProspectingCandidate::query()->create([
            'prospecting_search_job_id' => $exactJob,
            'purpose' => 'buyer_discovery',
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
            'working_name' => 'Legacy synthetic candidate',
            'normalized_name' => 'legacy synthetic candidate',
            'public_activity_summary' => 'Repository-owned fixture.',
            'relevance_summary' => 'Historical Good-first relevance.',
            'fingerprint_hash' => hash('sha256', 'legacy-candidate-fingerprint'),
            'normalized_payload_hash' => hash('sha256', 'legacy-candidate-payload'),
            'expires_at' => now()->addDay(),
        ]);
        $unit = $this->unit(['name' => 'Historical reconciliation Unit']);
        $context = UnitBusinessContext::query()->findOrFail(
            $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer'])['id'],
        );
        $exactMatch = $this->legacyGoodMatch($unit->id, $context->id, $exactGood->id, $actor->id);
        $missingMatch = $this->legacyGoodMatch($unit->id, $context->id, $missingGood->id, $actor->id);
        $ambiguousMatch = $this->legacyGoodMatch($unit->id, $context->id, $ambiguousGood->id, $actor->id);
        $unitsBefore = DB::table('units')->count();
        $entitiesBefore = Entity::query()->without(['buildings', 'classification', 'country'])->count();

        $dryRunExit = Artisan::call('ai-sales:reconcile-prospecting-products', ['--chunk' => 1]);
        $dryRunOutput = Artisan::output();
        $this->assertSame(0, $dryRunExit, $dryRunOutput);
        $this->assertStringContainsString('APP_ENV=testing', $dryRunOutput);
        $this->assertStringContainsString('DB_DRIVER=sqlite', $dryRunOutput);
        $this->assertStringContainsString('DB_DATABASE=:memory:', $dryRunOutput);
        foreach (['DO_NOT_PRINT_EXACT_GOOD', 'DO_NOT_PRINT_MISSING_GOOD', 'DO_NOT_PRINT_AMBIGUOUS_GOOD'] as $secretName) {
            $this->assertStringNotContainsString($secretName, $dryRunOutput);
        }
        $this->assertDatabaseCount('prospecting_search_job_products', 0);
        $this->assertDatabaseCount('prospecting_candidate_products', 0);
        $this->assertDatabaseCount('unit_product_matches', 0);
        $this->assertSame('legacy_unreconciled', DB::table('unit_good_matches')->where('id', $exactMatch)->value('compatibility_state'));

        $this->assertSame(1, Artisan::call('ai-sales:reconcile-prospecting-products', ['--apply' => true]), Artisan::output());
        $this->assertDatabaseCount('unit_product_matches', 0);

        $manualProduct = $this->product('Manual Stage08R Product');
        $manualGood = $this->good('Manual Stage08R Good');
        $manualGood->products()->attach($manualProduct->id);
        $manualJob = app(ProspectingSearchJobService::class)->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'New Product-first row must not be reconciled as legacy.',
            'primary_product_id' => $manualProduct->id,
            'originating_good_ids' => [$manualGood->id],
        ], $actor);
        $manualProductMatch = app(UnitProductMatchService::class)->suggest($unit, $context, [
            'product_id' => $manualProduct->id,
            'match_type' => UnitProductMatchType::PotentialNeed,
            'safe_rationale' => 'Manual Product relevance.',
        ], $actor);
        $manualFit = app(UnitGoodMatchService::class)->suggest($unit, $context, [
            'unit_product_match_id' => $manualProductMatch->id,
            'good_id' => $manualGood->id,
            'match_type' => UnitGoodMatchType::PotentialNeed,
            'fit_confidence' => 0,
            'safe_rationale' => 'Manual exact offer fit.',
        ], $actor);
        app(UnitGoodMatchService::class)->review($manualFit, GoodOfferFitStatus::Quoted, $actor);

        $this->assertSame(0, Artisan::call('ai-sales:reconcile-prospecting-products', [
            '--apply' => true,
            '--yes' => true,
            '--chunk' => 1,
        ]), Artisan::output());
        $this->assertDatabaseHas('prospecting_search_job_products', [
            'prospecting_search_job_id' => $exactJob,
            'product_id' => $exactProduct->id,
            'role' => 'primary',
            'source_origin' => 'legacy_good_mapping',
        ]);
        $this->assertDatabaseHas('prospecting_search_jobs', [
            'id' => $exactJob,
            'product_mapping_state' => 'mapped',
        ]);
        $this->assertDatabaseHas('prospecting_search_jobs', [
            'id' => $missingJob,
            'product_mapping_state' => 'missing_product_mapping',
        ]);
        $this->assertDatabaseHas('prospecting_search_jobs', [
            'id' => $ambiguousJob,
            'product_mapping_state' => 'ambiguous_product_mapping',
        ]);
        $this->assertDatabaseHas('prospecting_candidate_products', [
            'prospecting_candidate_id' => $candidate->id,
            'product_id' => $exactProduct->id,
            'source' => 'rule',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('unit_good_matches', [
            'id' => $exactMatch,
            'fit_status' => 'offer_candidate',
            'compatibility_state' => 'mapped',
        ]);
        $this->assertNotNull(DB::table('unit_good_matches')->where('id', $exactMatch)->value('unit_product_match_id'));
        $this->assertDatabaseHas('unit_good_matches', [
            'id' => $missingMatch,
            'unit_product_match_id' => null,
            'compatibility_state' => 'missing_product_mapping',
        ]);
        $this->assertDatabaseHas('unit_good_matches', [
            'id' => $ambiguousMatch,
            'unit_product_match_id' => null,
            'compatibility_state' => 'ambiguous_product_mapping',
        ]);
        $this->assertDatabaseHas('prospecting_search_job_goods', [
            'prospecting_search_job_id' => $manualJob->id,
            'good_id' => $manualGood->id,
            'role' => 'originating',
            'source_origin' => 'manual_review',
            'compatibility_state' => 'mapped',
        ]);
        $this->assertDatabaseHas('unit_good_matches', [
            'id' => $manualFit->id,
            'unit_product_match_id' => $manualProductMatch->id,
            'fit_status' => 'quoted',
            'compatibility_state' => 'mapped',
        ]);
        $this->assertSame($unitsBefore, DB::table('units')->count());
        $this->assertSame($entitiesBefore, Entity::query()->without(['buildings', 'classification', 'country'])->count());

        $counts = [
            DB::table('prospecting_search_job_products')->count(),
            DB::table('prospecting_candidate_products')->count(),
            DB::table('unit_product_matches')->count(),
            DB::table('unit_good_matches')->whereNotNull('unit_product_match_id')->count(),
        ];
        $this->assertSame(0, Artisan::call('ai-sales:reconcile-prospecting-products', [
            '--apply' => true,
            '--yes' => true,
            '--chunk' => 2,
        ]), Artisan::output());
        $this->assertSame($counts, [
            DB::table('prospecting_search_job_products')->count(),
            DB::table('prospecting_candidate_products')->count(),
            DB::table('unit_product_matches')->count(),
            DB::table('unit_good_matches')->whereNotNull('unit_product_match_id')->count(),
        ]);
        $this->assertDatabaseCount('prospecting_search_job_goods', 4);
        $this->assertDatabaseCount('unit_good_matches', 4);
    }

    public function test_reconciliation_apply_is_blocked_in_production_environment(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->assertSame(1, Artisan::call('ai-sales:reconcile-prospecting-products', [
                '--apply' => true,
                '--yes' => true,
            ]));
            $this->assertStringContainsString('Blocked', Artisan::output());
        } finally {
            app()->detectEnvironment(static fn () => $original);
        }
    }

    private function legacyJob(int $actorId, int $goodId): int
    {
        $jobId = DB::table('prospecting_search_jobs')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'created_by' => $actorId,
            'owner_user_id' => $actorId,
            'reviewer_user_id' => $actorId,
            'purpose' => 'buyer_discovery',
            'lane' => 'sales',
            'default_role_code' => 'prospective_customer',
            'primary_good_id' => $goodId,
            'safe_objective' => 'Historical repository fixture.',
            'schema_hash' => hash('sha256', 'legacy-job-'.$goodId),
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('prospecting_search_job_goods')->insert([
            'prospecting_search_job_id' => $jobId,
            'good_id' => $goodId,
            'role' => 'primary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $jobId;
    }

    private function legacyGoodMatch(int $unitId, int $contextId, int $goodId, int $actorId): int
    {
        return DB::table('unit_good_matches')->insertGetId([
            'unit_id' => $unitId,
            'unit_business_context_id' => $contextId,
            'good_id' => $goodId,
            'match_type' => 'potential_need',
            'relevance' => 65,
            'confidence' => 60,
            'safe_rationale' => 'Historical Good-first repository fixture.',
            'evidence_reference' => 'repository-fixture:stage08-legacy',
            'evidence_hash' => hash('sha256', 'legacy-good-match-'.$goodId),
            'status' => 'suggested',
            'origin' => 'manual',
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function product(string $name): Product
    {
        return Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => $name,
            'is_published' => true,
        ]);
    }

    private function good(string $name): Good
    {
        return Good::query()->create(['name' => $name, 'is_published' => true]);
    }
}
