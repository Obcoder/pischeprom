<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Domain\AiSales\FindBuyers\BuildFindBuyersQueryPlan;
use App\Domain\AiSales\FindBuyers\FindBuyersDraftOrchestrator;
use App\Domain\AiSales\FindBuyers\SubmitFindBuyersPlanForReview;
use App\Domain\AiSales\Scoring\ProspectingScoreRecalculationService;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Models\Product;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingPublicResearchRecord;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchResult;
use App\Models\UnitBusinessContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FindBuyersProgressIsolationTest extends Stage11TestCase
{
    public function test_progress_is_a_safe_existing_table_projection_with_dual_lane_score_isolation(): void
    {
        $actor = $this->prospectingUser(['sales', 'procurement'], [
            'ai_sales.scoring.view', 'ai_sales.scoring.recalculate',
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи', 'eng' => 'Broccoli', 'is_published' => true,
        ]);
        $draft = app(FindBuyersDraftOrchestrator::class)->create([
            'source_type' => 'product', 'source_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
            'limits' => [
                'max_queries' => 2, 'max_results_per_query' => 2, 'max_domains' => 2,
                'max_page_fetch_attempts' => 1, 'max_candidates' => 2,
            ],
        ], $actor)->job;
        app(BuildFindBuyersQueryPlan::class)->handle($draft, $actor);
        $review = app(SubmitFindBuyersPlanForReview::class)->handle($draft->fresh(), $actor);
        $job = app(ProspectingSearchJobService::class)->approve($review->fresh(), $actor);
        app(ApproveProspectingQueryPlan::class)->handle($job, $actor);
        $query = $job->queries()->where('plan_status', 'approved')->firstOrFail();

        $execution = ProspectingSearchExecution::query()->create([
            'prospecting_search_job_id' => $job->id,
            'prospecting_search_query_id' => $query->id,
            'initiated_by' => $actor->id,
            'profile_code' => 'prospecting_b2b_discovery',
            'provider_code' => 'fake',
            'request_hash' => hash('sha256', 'stage11-progress-request'),
            'plan_hash' => $query->plan_hash,
            'status' => 'completed',
            'attempt' => 1,
            'request_count' => 1,
            'result_count' => 1,
            'duplicate_count' => 0,
            'blocked_result_count' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $result = ProspectingSearchResult::query()->create([
            'prospecting_search_execution_id' => $execution->id,
            'prospecting_search_job_id' => $job->id,
            'prospecting_search_query_id' => $query->id,
            'rank' => 1,
            'title' => 'RAW TITLE MUST NOT LEAK',
            'snippet' => 'RAW SNIPPET AND contact@example.test MUST NOT LEAK',
            'url' => 'https://buyer.synthetic.example/about?private=raw',
            'canonical_url' => 'https://buyer.synthetic.example/about',
            'url_hash' => hash('sha256', 'https://buyer.synthetic.example/about'),
            'registrable_domain' => 'synthetic.example',
            'domain_hash' => hash('sha256', 'synthetic.example'),
            'result_hash' => hash('sha256', 'stage11-progress-result'),
            'trust_level' => 'untrusted',
            'instruction_authority' => 'none',
            'fetch_status' => 'blocked',
            'research_status' => 'failed',
        ]);
        ProspectingPublicFetch::query()->create([
            'prospecting_search_result_id' => $result->id,
            'status' => 'blocked',
            'trust_level' => 'untrusted',
            'instruction_authority' => 'none',
            'error_category' => 'content_policy',
            'error_code' => 'page_dtd_blocked',
        ]);
        ProspectingPublicResearchRecord::query()->create([
            'prospecting_search_result_id' => $result->id,
            'workflow_code' => 'public_company_research.v1',
            'workflow_version' => 'stage09-v1',
            'workflow_hash' => hash('sha256', 'stage11-research-workflow'),
            'status' => 'failed',
            'input_hash' => hash('sha256', 'stage11-research-input'),
            'schema_valid' => false,
            'provider_code' => 'fake',
            'error_category' => 'safe_failure',
            'error_code' => 'synthetic_research_blocked',
        ]);
        $candidate = app(ProspectingCandidateService::class)->createFixture($job, [
            'working_name' => 'Repository Synthetic Buyer',
            'website' => 'https://buyer-stage11.example',
            'public_activity_summary' => 'Synthetic food manufacturer.',
            'relevance_summary' => 'Public evidence of Broccoli use.',
            'confidence_components' => ['relevance' => 88, 'identity' => 80],
            'product_ids' => [$product->id],
            'sources' => [[
                'type' => 'synthetic_fixture',
                'reference' => 'repository-fixture:stage11:buyer',
                'title' => 'Synthetic public evidence',
                'excerpt' => 'Repository-owned evidence only.',
            ]],
        ], $actor, true, $query);
        $result->update(['prospecting_candidate_id' => $candidate->id]);
        $unit = app(ResolveProspectingCandidate::class)->createNewUnit($candidate, $actor);
        $salesContext = $unit->businessContexts()->where('lane', 'sales')->firstOrFail();
        $salesMatch = $salesContext->productMatches()->where('product_id', $product->id)->firstOrFail();
        $salesContext->update(['stage' => 'do_not_contact']);
        $scores = app(ProspectingScoreRecalculationService::class);
        $scores->product($actor, $salesMatch);
        $scores->priority($actor, $salesContext->fresh());

        $procurementContext = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id,
            'lane' => 'procurement',
            'role_code' => 'prospective_supplier',
            'stage' => 'researching',
            'status' => 'active',
            'source' => 'repository_fixture',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $candidateProduct = $candidate->products()->where('product_id', $product->id)->firstOrFail();
        $procurementMatch = app(UnitProductMatchService::class)->suggest($unit, $procurementContext, [
            'product_id' => $product->id,
            'prospecting_candidate_product_id' => $candidateProduct->id,
            'match_type' => UnitProductMatchType::PotentialOffer,
            'safe_rationale' => 'PROCUREMENT SECRET MUST NOT LEAK',
            'evidence_reference' => 'fixture:stage11:procurement-only',
            'evidence_hash' => hash('sha256', 'fixture:stage11:procurement-only'),
        ], $actor);
        $procurementMatch = app(UnitProductMatchService::class)->review($procurementMatch, UnitProductMatchStatus::Reviewed, $actor);
        $scores->product($actor, $procurementMatch);
        $scores->priority($actor, $procurementContext);

        $payload = $this->actingAs($actor)
            ->getJson("/api/ai-sales/find-buyers/jobs/{$job->public_id}/progress")
            ->assertOk()
            ->assertJsonPath('data.stage', 'scored')
            ->assertJsonPath('data.counts.executions.request_count', 1)
            ->assertJsonPath('data.counts.fetches.partial_or_fail_closed', 1)
            ->assertJsonPath('data.fetch_outcomes.0.error_code', 'page_dtd_blocked')
            ->assertJsonPath('data.counts.matches.unit_product_matches', 1)
            ->assertJsonPath('data.counts.matches.sales_contexts', 1)
            ->assertJsonPath('data.counts.scores.prospect_priority_snapshots', 1)
            ->assertJsonPath('data.scoring.prospect_priority.0.eligibility', 'blocked_do_not_contact')
            ->assertJsonPath('data.candidates.0.review_url', '/Ameise/ai-sales?tab=review&candidate='.$candidate->public_id.'#candidate-review')
            ->assertJsonPath('data.source_of_truth.progress_is_projection', true)
            ->assertJsonPath('data.source_of_truth.copied_event_rows', 0)
            ->json('data');
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('RAW TITLE', $encoded);
        $this->assertStringNotContainsString('RAW SNIPPET', $encoded);
        $this->assertStringNotContainsString('contact@example.test', $encoded);
        $this->assertStringNotContainsString('PROCUREMENT SECRET', $encoded);
        $this->assertStringNotContainsString('private=raw', $encoded);
        Http::assertNothingSent();
    }
}
