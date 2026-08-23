<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignMetrics;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignSearchJobService;
use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Jobs\AiSales\ExecuteProspectingSearchQueryJob;
use App\Models\Entity;
use App\Models\Unit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class ReviewedQueryPlanCustomizationTest extends Stage14TestCase
{
    public function test_reviewer_can_rebuild_current_campaign_plan_with_more_code_owned_queries_without_execution(): void
    {
        [$actor, $campaign, $run, $job, $initialQueries] = $this->waitingPlanFixture();
        $this->assertCount(3, $initialQueries);
        $initialSchemaHash = $job->schema_hash;
        $unitsBefore = Unit::query()->count();
        $entitiesBefore = Entity::query()->count();

        $payload = [
            'target_query_count' => 10,
            'buyer_archetypes' => [
                'pharmaceutical_manufacturer',
                'food_manufacturer',
                'household_chemicals_manufacturer',
                'cosmetics_manufacturer',
            ],
            'intents' => [
                'product_usage_evidence',
                'company_discovery',
                'manufacturer_discovery',
            ],
        ];
        $url = '/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search-plan/rebuild';
        $response = $this->actingAs($actor)->postJson($url, $payload);

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.query_count', 10)
            ->assertJsonPath('meta.external_http', 0)
            ->assertJsonPath('meta.execution_started', false);
        $job->refresh();
        $preferences = $job->criteria_snapshot['query_plan_preferences'];
        $this->assertSame('reviewed-query-plan-preferences-v1', $preferences['version']);
        $this->assertArrayNotHasKey('base_schema_hash', $preferences);
        $this->assertSame(10, $preferences['target_query_count']);
        $this->assertSame([
            'food_manufacturer',
            'cosmetics_manufacturer',
            'pharmaceutical_manufacturer',
            'household_chemicals_manufacturer',
        ], $preferences['buyer_archetypes']);
        $this->assertNotSame($initialSchemaHash, $job->schema_hash);
        $reviewedSchemaHash = $job->schema_hash;
        $this->assertSame(10, $job->queries()->where('plan_status', 'review_required')->count());
        $this->assertGreaterThanOrEqual(1, $job->queries()->where('plan_status', 'stale')->count());
        $this->assertSame(0, $job->searchExecutions()->count());
        $this->assertSame(0, $job->searchResults()->count());
        $this->assertSame(0, $job->candidates()->count());
        $this->assertSame($unitsBefore, Unit::query()->count());
        $this->assertSame($entitiesBefore, Entity::query()->count());
        $this->assertSame('requires_action', $run->fresh()->status->value);
        $this->assertSame('query_plan_review_required', $run->fresh()->safe_error_code);
        $this->assertSame('running', $campaign->fresh()->status->value);

        $queries = $job->queries()->where('plan_status', 'review_required')->orderBy('sequence')->get();
        $this->assertCount(10, $queries->pluck('safe_display_query')->unique());
        $this->assertTrue($queries->contains(fn ($query): bool => str_contains(mb_strtolower($query->safe_display_query), 'космет')));
        $this->assertTrue($queries->contains(fn ($query): bool => str_contains(mb_strtolower($query->safe_display_query), 'фармацевт')));
        $this->assertTrue($queries->contains(fn ($query): bool => str_contains(mb_strtolower($query->safe_display_query), 'бытовой химии')));

        $review = collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor))
            ->firstWhere('category', 'query_plan_review');
        $this->assertSame(10, $review['safe_evidence']['query_count']);
        $this->assertSame(10, $review['safe_evidence']['max_queries']);
        $this->assertSame(10, $review['safe_evidence']['preferences']['target_query_count']);
        $this->assertContains('cosmetics_manufacturer', collect($review['safe_evidence']['available_archetypes'])->pluck('code'));
        $this->assertContains('procurement_evidence', collect($review['safe_evidence']['available_intents'])->pluck('code'));
        $this->assertArrayNotHasKey('base_schema_hash', $review['safe_evidence']['preferences']);
        $this->assertSame(10, app(ClientAcquisitionCampaignMetrics::class)->get($campaign->fresh(), $actor)['queries']['planned']);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search')
            ->assertOk()->assertJsonCount(10, 'data.queries');

        $hashes = $queries->pluck('query_hash')->all();
        $staleSequences = $job->queries()->where('plan_status', 'stale')->orderBy('id')->pluck('sequence', 'id')->all();
        $this->actingAs($actor)->postJson($url, $payload)->assertOk()->assertJsonCount(10, 'data');
        $this->assertSame($reviewedSchemaHash, $job->fresh()->schema_hash);
        $this->assertSame($hashes, $job->fresh()->queries()->where('plan_status', 'review_required')->orderBy('sequence')->pluck('query_hash')->all());
        $this->assertSame($staleSequences, $job->fresh()->queries()->whereIn('id', array_keys($staleSequences))->orderBy('id')->pluck('sequence', 'id')->all());

        $this->actingAs($actor)->postJson(
            '/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search-plan/approve',
        )->assertOk()->assertJsonPath('meta.campaign_resume_queued', true);
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, 1);
        config()->set([
            'ai-sales.campaigns.live_search_enabled' => true,
            'ai-sales.web_search_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
        ]);
        $searchOutcome = app(ClientAcquisitionCampaignStageProcessorInterface::class)->process(
            $campaign->fresh(),
            $run->fresh(),
            $run->steps()->where('sequence', 4)->firstOrFail(),
            $actor,
        );
        $this->assertSame('pending', $searchOutcome->kind);
        $this->assertSame('search_jobs_dispatched', $searchOutcome->safeCode);
        Queue::assertPushed(ExecuteProspectingSearchQueryJob::class, 10);
        $staleIds = $job->fresh()->queries()->where('plan_status', 'stale')->pluck('id');
        Queue::assertNotPushed(
            ExecuteProspectingSearchQueryJob::class,
            fn (ExecuteProspectingSearchQueryJob $queued): bool => $staleIds->contains($queued->queryId),
        );
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_rebuild_rejects_arbitrary_queries_invalid_options_excess_counts_and_settled_plans(): void
    {
        [$actor, , , $job] = $this->waitingPlanFixture();
        $url = '/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search-plan/rebuild';
        $valid = [
            'target_query_count' => 3,
            'buyer_archetypes' => ['food_manufacturer'],
            'intents' => ['company_discovery', 'product_usage_evidence', 'institutional_buyer'],
        ];

        $this->postJson($url, $valid)->assertUnauthorized();
        $this->actingAs($actor)->postJson($url, [...$valid, 'query' => 'произвольный запрос'])
            ->assertUnprocessable()->assertJsonValidationErrors('query');
        $this->actingAs($actor)->postJson($url, [...$valid, 'buyer_archetypes' => ['browser_owned']])
            ->assertUnprocessable()->assertJsonValidationErrors('buyer_archetypes.0');
        $this->actingAs($actor)->postJson($url, [...$valid, 'target_query_count' => 11])
            ->assertUnprocessable()->assertJsonValidationErrors('target_query_count');
        $this->actingAs($actor)->postJson($url, [
            ...$valid,
            'target_query_count' => 4,
            'intents' => ['company_discovery'],
        ])->assertUnprocessable()->assertJsonValidationErrors('target_query_count');

        $unauthorized = $this->userWith([
            'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.prospecting.view',
        ]);
        $this->actingAs($unauthorized)->postJson($url, $valid)->assertForbidden();
        $missingSearchReview = $this->prospectingUser();
        $missingSearchReview->revokePermissionTo('ai_sales.search.review', 'crm');
        $this->actingAs($missingSearchReview)->postJson($url, $valid)->assertForbidden();

        $this->actingAs($actor)->postJson($url, $valid)->assertOk();
        app(ApproveProspectingQueryPlan::class)->handle($job->fresh(), $actor);
        $this->actingAs($actor)->postJson($url, $valid)
            ->assertUnprocessable()->assertJsonValidationErrors('query_plan');
        $this->assertSame(0, $job->searchExecutions()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_campaign_review_ui_exposes_bounded_plan_customization_without_browser_execution_fields(): void
    {
        $ui = file_get_contents(resource_path('js/Components/AiSales/ClientAcquisitionCampaignDashboard.vue'));
        $request = file_get_contents(app_path('Http/Requests/AiSales/RebuildProspectingQueryPlanRequest.php'));

        $this->assertStringContainsString('Настроить и пересобрать запросы', $ui);
        $this->assertStringContainsString('Пересобрать план без запуска Yandex', $ui);
        $this->assertStringContainsString('/search-plan/rebuild', $ui);
        $this->assertStringNotContainsString('search-execute', $ui);
        $this->assertStringContainsString("'query' => ['prohibited']", $request);
        $this->assertStringContainsString("'url' => ['prohibited']", $request);
        $this->assertStringContainsString("'provider' => ['prohibited']", $request);
    }

    private function waitingPlanFixture(): array
    {
        Queue::fake();
        Http::fake();
        Mail::fake();
        $actor = $this->campaignUser();
        $product = $this->campaignProduct('Glycerin-like Product');
        $campaign = $this->approvedCampaign($actor, $product, [
            'criteria' => [
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 10,
                'max_page_fetch_attempts' => 10,
                'max_results_per_query' => 10,
            ],
            'limits' => [
                ...$this->campaignLimits(),
                'max_search_requests_per_run' => 10,
                'max_search_requests_per_day' => 20,
                'max_search_requests_per_month' => 100,
            ],
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'reviewed-plan-'.uniqid());
        $job = app(ClientAcquisitionCampaignSearchJobService::class)->ensure($campaign, $run);
        $queries = app(PlanProspectingQueries::class)->handle($job, $actor);
        $run->steps()->where('sequence', '<', 4)->update(['status' => 'completed', 'completed_at' => now()]);
        $run->steps()->where('sequence', 4)->update([
            'status' => 'requires_action',
            'safe_error_code' => 'query_plan_review_required',
            'safe_error_summary' => 'Human review of the current code-owned query plan is required.',
        ]);
        $run->update([
            'status' => 'requires_action',
            'current_step' => 4,
            'safe_error_code' => 'query_plan_review_required',
            'safe_error_summary' => 'Human review of the current code-owned query plan is required.',
        ]);

        return [$actor, $campaign->fresh(), $run->fresh(), $job->fresh(), $queries];
    }
}
