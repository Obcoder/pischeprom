<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\ProductMappingState;
use App\Domain\AiSales\Enums\UnitGoodMatchType;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Domain\AiSales\Services\UnitGoodMatchService;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Product;
use App\Models\UnitBusinessContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductFirstProspectingTest extends Stage08TestCase
{
    public function test_job_api_uses_published_product_scope_and_filters_optional_goods_by_product(): void
    {
        $actor = $this->prospectingUser();
        $product = $this->publishedProduct('Основной продукт');
        $otherProduct = $this->publishedProduct('Другой продукт');
        $good = $this->publishedGood('Offer for primary Product');
        $otherGood = $this->publishedGood('Offer for another Product');
        $good->products()->attach($product->id);
        $otherGood->products()->attach($otherProduct->id);

        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products?search='.urlencode('Основной'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
        $this->actingAs($actor)->getJson("/api/ai-sales/prospecting/catalog/products/{$product->id}/goods")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $good->id);

        $response = $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/jobs', [
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Product-first repository fixture.',
            'primary_product_id' => $product->id,
            'originating_good_ids' => [$good->id],
        ])->assertCreated();
        $response->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.products.0.role', 'primary')
            ->assertJsonPath('data.originating_goods.0.id', $good->id)
            ->assertJsonPath('data.product_mapping_state', ProductMappingState::Mapped->value);

        $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/jobs', [
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Legacy Good-first browser input must fail.',
            'primary_good_id' => $good->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('primary_good_id');
        $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/jobs', [
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Modern Good-only browser input must fail.',
            'originating_good_ids' => [$good->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('originating_good_ids');
    }

    public function test_zero_many_and_scope_mismatch_good_mappings_never_guess_a_product(): void
    {
        $actor = $this->prospectingUser();
        $selected = $this->publishedProduct('Selected Product');
        $other = $this->publishedProduct('Other Product');
        $missing = $this->publishedGood('Missing mapping Good');
        $ambiguous = $this->publishedGood('Ambiguous mapping Good');
        $ambiguous->products()->attach([$selected->id, $other->id]);
        $mismatch = $this->publishedGood('Mismatch mapping Good');
        $mismatch->products()->attach($other->id);
        $service = app(ProspectingSearchJobService::class);

        foreach ([
            [$missing, ProductMappingState::MissingProductMapping],
            [$ambiguous, ProductMappingState::AmbiguousProductMapping],
            [$mismatch, ProductMappingState::ProductScopeMismatch],
        ] as [$good, $expected]) {
            $job = $service->createDraft([
                'purpose' => 'buyer_discovery',
                'safe_objective' => 'Mapping must remain review-required.',
                'primary_product_id' => $selected->id,
                'originating_good_ids' => [$good->id],
            ], $actor);
            $this->assertSame($expected, $job->product_mapping_state);
            $service->submit($job, $actor);

            try {
                $service->approve($job->fresh(), $actor);
                $this->fail("{$expected->value} mapping was approved.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('originating_good_ids', $exception->errors());
            }
        }
    }

    public function test_approval_requires_primary_product_and_candidate_products_are_a_job_subset(): void
    {
        $actor = $this->prospectingUser();
        $primary = $this->publishedProduct('Candidate primary Product');
        $additional = $this->publishedProduct('Candidate additional Product');
        $outside = $this->publishedProduct('Outside Product');
        $good = $this->publishedGood('Legacy Good-only scope');
        $good->products()->attach($primary->id);
        $service = app(ProspectingSearchJobService::class);
        $goodOnly = $service->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Good-only compatibility input must not be approvable.',
            'primary_good_id' => $good->id,
        ], $actor);
        $legacyPivot = DB::table('prospecting_search_job_goods')
            ->where('prospecting_search_job_id', $goodOnly->id)->first();
        $service->updateDraft($goodOnly, [
            'safe_objective' => 'Unrelated draft edit must preserve legacy Good provenance.',
        ], $actor);
        $this->assertSame('legacy_api_compatibility', DB::table('prospecting_search_job_goods')->where('id', $legacyPivot->id)->value('source_origin'));
        $this->assertSame('originating', DB::table('prospecting_search_job_goods')->where('id', $legacyPivot->id)->value('role'));
        $this->assertSame($good->id, $goodOnly->fresh()->primary_good_id);
        $service->submit($goodOnly, $actor);
        try {
            $service->approve($goodOnly->fresh(), $actor);
            $this->fail('Good-only Job was approved without a Product.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_product_id', $exception->errors());
        }

        try {
            $service->createDraft([
                'purpose' => 'buyer_discovery',
                'safe_objective' => 'Conflicting Product scope.',
                'primary_product_id' => $primary->id,
                'excluded_product_ids' => [$primary->id],
            ], $actor);
            $this->fail('Selected and excluded Product conflict was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('excluded_product_ids', $exception->errors());
        }

        $job = $service->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Bounded multi-Product scope.',
            'primary_product_id' => $primary->id,
            'additional_product_ids' => [$additional->id],
            'criteria' => ['categories' => ['Must not infer another Product']],
        ], $actor);
        DB::table('prospecting_search_job_products')
            ->where('prospecting_search_job_id', $job->id)
            ->where('product_id', $primary->id)
            ->update(['source_origin' => 'legacy_good_mapping']);
        $job = $service->updateDraft($job, [
            'safe_objective' => 'Unrelated edit preserves reconciled Product provenance.',
        ], $actor);
        $this->assertSame('legacy_good_mapping', DB::table('prospecting_search_job_products')
            ->where('prospecting_search_job_id', $job->id)
            ->where('product_id', $primary->id)
            ->value('source_origin'));
        $service->submit($job, $actor);
        $job = $service->approve($job->fresh(), $actor);
        $this->assertEqualsCanonicalizing(
            [$primary->id, $additional->id],
            $job->products()->wherePivotIn('role', ['primary', 'additional'])->pluck('products.id')->all(),
        );
        $candidate = app(ProspectingCandidateService::class)->createFixture($job, [
            ...$this->candidateFixture('Candidate Product subset'),
            'product_ids' => [$additional->id],
        ], $actor, true);
        $this->assertSame([$additional->id], $candidate->products()->pluck('product_id')->all());

        try {
            app(ProspectingCandidateService::class)->createFixture($job, [
                ...$this->candidateFixture('Outside Candidate Product'),
                'product_ids' => [$outside->id],
            ], $actor, true);
            $this->fail('Candidate accepted a Product outside its approved Job scope.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_ids', $exception->errors());
        }
    }

    public function test_candidate_resolution_creates_product_match_without_requiring_good_or_entity(): void
    {
        $actor = $this->prospectingUser();
        $product = $this->publishedProduct('Product-only discovery');
        $jobs = app(ProspectingSearchJobService::class);
        $job = $jobs->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Resolve a Product-only candidate.',
            'primary_product_id' => $product->id,
        ], $actor);
        $jobs->submit($job, $actor);
        $job = $jobs->approve($job->fresh(), $actor);
        $candidate = app(ProspectingCandidateService::class)->createFixture($job, [
            'working_name' => 'Synthetic Product-only Candidate',
            'website' => 'https://product-only-stage08r.example',
            'public_activity_summary' => 'Fictional public activity.',
            'relevance_summary' => 'Public evidence of relevance to the selected Product.',
            'confidence_components' => ['relevance' => 77, 'identity' => 70],
            'sources' => [[
                'type' => 'synthetic_fixture',
                'reference' => 'repository-fixture:stage08r-product-only',
                'title' => 'Synthetic Product evidence',
                'excerpt' => 'Repository-owned fictional evidence.',
            ]],
        ], $actor, true);

        $this->assertDatabaseHas('prospecting_candidate_products', [
            'prospecting_candidate_id' => $candidate->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'source' => 'job',
        ]);
        $entityCount = Entity::query()->without(['buildings', 'classification', 'country'])->count();
        $contextFreeProductUnitCount = DB::table('product_unit')->count();
        $unit = app(ResolveProspectingCandidate::class)->createNewUnit($candidate, $actor);
        $context = $unit->businessContexts()->where('lane', 'sales')->firstOrFail();

        $this->assertDatabaseHas('unit_product_matches', [
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'product_id' => $product->id,
            'match_type' => 'potential_need',
            'origin' => 'candidate',
        ]);
        $this->assertDatabaseCount('unit_good_matches', 0);
        $this->assertSame($contextFreeProductUnitCount, DB::table('product_unit')->count());
        $this->assertSame($entityCount, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->actingAs($actor)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$context->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.product_matches')
            ->assertJsonCount(0, 'data.good_offer_fits')
            ->assertJsonPath('data.product_matches.0.product.id', $product->id);
    }

    public function test_product_match_review_is_authenticated_permissioned_and_lane_isolated(): void
    {
        $actor = $this->prospectingUser(['sales', 'procurement']);
        $unit = $this->unit(['name' => 'Dual-lane Product fixture']);
        $sales = UnitBusinessContext::query()->findOrFail(
            $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer'])['id'],
        );
        $procurement = UnitBusinessContext::query()->findOrFail(
            $this->createContext($actor, $unit, ['lane' => 'procurement', 'role_code' => 'prospective_supplier'])['id'],
        );
        $product = $this->publishedProduct('Dual-lane Product');
        $service = app(UnitProductMatchService::class);
        $salesMatch = $service->suggest($unit, $sales, [
            'product_id' => $product->id,
            'match_type' => UnitProductMatchType::PotentialNeed,
            'safe_rationale' => 'Public sales-lane Product evidence.',
        ], $actor);
        $procurementMatch = $service->suggest($unit, $procurement, [
            'product_id' => $product->id,
            'match_type' => UnitProductMatchType::PotentialOffer,
            'safe_rationale' => 'Public procurement-lane Product evidence.',
        ], $actor);
        $salesReplay = $service->suggest($unit, $sales, [
            'product_id' => $product->id,
            'match_type' => UnitProductMatchType::PotentialNeed,
            'safe_rationale' => 'Duplicate Product match must reuse the active row.',
        ], $actor);
        $this->assertSame($salesMatch->id, $salesReplay->id);
        $this->assertSame(2, $unit->productMatches()->count());

        app('auth')->forgetGuards();
        $this->postJson("/api/ai-sales/prospecting/product-matches/{$salesMatch->id}/review", [
            'status' => 'reviewed',
        ])->assertUnauthorized();
        $salesOnly = $this->prospectingUser(['sales']);
        $this->actingAs($salesOnly)
            ->postJson("/api/ai-sales/prospecting/product-matches/{$procurementMatch->id}/review", ['status' => 'reviewed'])
            ->assertForbidden();
        $this->actingAs($salesOnly)
            ->postJson("/api/ai-sales/prospecting/product-matches/{$salesMatch->id}/review", ['status' => 'reviewed'])
            ->assertOk()->assertJsonPath('data.status', 'reviewed');
        $this->actingAs($salesOnly)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$procurement->id}")
            ->assertForbidden();
        $this->actingAs($actor)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$sales->id}")
            ->assertOk()->assertJsonCount(1, 'data.product_matches');
        $this->actingAs($actor)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$procurement->id}")
            ->assertOk()->assertJsonCount(1, 'data.product_matches');
    }

    public function test_new_good_offer_fit_requires_an_exact_product_match_and_legacy_rows_are_permission_gated(): void
    {
        $actor = $this->prospectingUser(['sales']);
        $unit = $this->unit(['name' => 'Good offer-fit boundary']);
        $context = UnitBusinessContext::query()->findOrFail(
            $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer'])['id'],
        );
        $product = $this->publishedProduct('Offer-fit Product');
        $otherProduct = $this->publishedProduct('Other offer-fit Product');
        $exact = $this->publishedGood('Exact offer fit');
        $missing = $this->publishedGood('Missing Product offer');
        $ambiguous = $this->publishedGood('Ambiguous Product offer');
        $wrong = $this->publishedGood('Wrong Product offer');
        $exact->products()->attach($product->id);
        $ambiguous->products()->attach([$product->id, $otherProduct->id]);
        $wrong->products()->attach($otherProduct->id);
        $productMatch = app(UnitProductMatchService::class)->suggest($unit, $context, [
            'product_id' => $product->id,
            'match_type' => UnitProductMatchType::PotentialNeed,
            'safe_rationale' => 'Public Product relevance evidence.',
        ], $actor);
        $service = app(UnitGoodMatchService::class);

        try {
            $service->suggest($unit, $context, [
                'good_id' => $exact->id,
                'match_type' => UnitGoodMatchType::PotentialNeed,
                'safe_rationale' => 'Missing Product-match reference.',
            ], $actor);
            $this->fail('Good offer fit was created without a Product match.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('unit_good_matches', 0);
        }
        foreach ([$missing, $ambiguous, $wrong] as $invalidGood) {
            try {
                $service->suggest($unit, $context, [
                    'unit_product_match_id' => $productMatch->id,
                    'good_id' => $invalidGood->id,
                    'match_type' => UnitGoodMatchType::PotentialNeed,
                    'safe_rationale' => 'Invalid Product mapping must not be guessed.',
                ], $actor);
                $this->fail('Incompatible Good offer fit was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('good_id', $exception->errors());
            }
        }
        $fit = $service->suggest($unit, $context, [
            'unit_product_match_id' => $productMatch->id,
            'good_id' => $exact->id,
            'match_type' => UnitGoodMatchType::PotentialNeed,
            'fit_confidence' => 0,
            'safe_rationale' => 'Exact commercial offer remains unscored.',
        ], $actor);
        $this->assertSame($productMatch->id, $fit->unit_product_match_id);
        $this->assertSame('offer_candidate', $fit->fit_status->value);
        $this->assertSame(1, $unit->productMatches()->count());

        DB::table('unit_good_matches')->insert([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'good_id' => $missing->id,
            'match_type' => 'potential_need',
            'relevance' => 1,
            'safe_rationale' => 'Legacy permission-gated diagnostic.',
            'evidence_hash' => hash('sha256', 'legacy-stage08r-permission-test'),
            'status' => 'suggested',
            'origin' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $viewer = $this->prospectingUser(['sales']);
        $this->actingAs($viewer)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$context->id}")
            ->assertOk()->assertJsonCount(0, 'data.legacy_good_match_diagnostics');
        $internal = $this->prospectingUser(['sales'], ['ai_sales.classifications.view_internal']);
        $this->actingAs($internal)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$context->id}")
            ->assertOk()->assertJsonCount(1, 'data.legacy_good_match_diagnostics');
    }

    private function publishedProduct(string $name): Product
    {
        return Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => $name,
            'eng' => $name.' EN',
            'is_published' => true,
        ]);
    }

    private function publishedGood(string $name): Good
    {
        return Good::query()->create(['name' => $name, 'is_published' => true]);
    }

    private function candidateFixture(string $name): array
    {
        return [
            'working_name' => $name,
            'website' => 'https://'.strtolower(str_replace(' ', '-', $name)).'.example',
            'public_activity_summary' => 'Fictional public activity.',
            'relevance_summary' => 'Fictional Product relevance.',
            'confidence_components' => ['relevance' => 70, 'identity' => 65],
            'sources' => [[
                'type' => 'synthetic_fixture',
                'reference' => 'repository-fixture:stage08r-candidate-subset',
                'title' => 'Synthetic Product evidence',
                'excerpt' => 'Repository-owned fictional evidence.',
            ]],
        ];
    }
}
