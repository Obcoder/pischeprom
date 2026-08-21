<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\AdvanceClientAcquisitionCampaignRun;
use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignSearchJobService;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Models\AiAgentRun;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingPublicResearchRecord;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchResult;
use App\Models\ProspectingSearchUsageRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

final class Stage14ActivationCResumeTest extends Stage14TestCase
{
    public function test_find_buyers_read_projections_coexist_with_production_shaped_activation_c_state(): void
    {
        [$actor, $campaign, $run, $job] = $this->activationCFixture([
            'completed',
            'completed',
            'page_dtd_blocked',
        ]);
        Http::fake();
        $before = $this->sideEffectCounts($job);
        $productId = (int) $campaign->products()->wherePivot('role', 'primary')->value('products.id');

        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard?limit=25')
            ->assertOk()
            ->assertJsonPath('data.runtime.search_execution_enabled', true)
            ->assertJsonPath('data.live_execution_action_available', false)
            ->assertJsonFragment(['id' => $job->public_id]);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=product&source_id='.$productId)
            ->assertOk()
            ->assertJsonPath('data.runtime.search_execution_enabled', true)
            ->assertJsonPath('data.runtime.live_execution_allowed', false);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/jobs/'.$job->public_id.'/progress')
            ->assertOk()
            ->assertJsonPath('data.counts.executions.total', 1)
            ->assertJsonPath('data.counts.executions.request_count', 1)
            ->assertJsonPath('data.counts.results.total', 10)
            ->assertJsonPath('data.counts.fetches.total', 3)
            ->assertJsonPath('data.counts.research.total', 0);
        $projection = $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search')
            ->assertOk()
            ->assertJsonCount(10, 'data.results')
            ->assertJsonPath('data.candidate_ingestion_review.job_id', $job->public_id)
            ->assertJsonPath('data.candidate_ingestion_review.budget.source', 'campaign')
            ->assertJsonPath('data.candidate_ingestion_review.budget.current', true)
            ->assertJsonPath('data.candidate_ingestion_review.budget.pages_limit', 3)
            ->assertJsonPath('data.candidate_ingestion_review.budget.pages_used', 3);
        $this->assertNotEmpty($projection->json('data.candidate_ingestion_review.domains'));

        $researchReview = collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor))
            ->where('category', 'public_research_review')->sole();
        $this->assertSame($job->id, $researchReview['search_job_id']);
        $this->assertSame($job->public_id, $researchReview['search_job_public_id']);

        $this->assertSame($before, $this->sideEffectCounts($job));
        $this->assertSame(AiRunStatus::RequiresAction, $run->fresh()->status);
        $this->assertSame('public_research_review_required', $run->fresh()->safe_error_code);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_research_stage_backfills_past_ineligible_top_ranked_results(): void
    {
        [$actor, , $run, $job] = $this->activationCFixture([]);
        $job->searchResults()->get()->each(fn (ProspectingSearchResult $result) => $result->update([
            'title' => 'Оптовый поставщик — купить оптом',
            'snippet' => 'Продажа оптом и прайс-лист.',
        ]));
        $buyer = $job->searchResults()->where('rank', 4)->firstOrFail();
        $buyer->update([
            'title' => 'North Food Factory | About',
            'snippet' => 'Manufacturer of ready meals and frozen products.',
        ]);
        $this->createFetch($buyer->fresh(), 'completed');

        $resumed = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::RequiresAction, $resumed->status);
        $this->assertSame('candidate_ingestion_review_required', $resumed->safe_error_code);
        $this->assertDatabaseHas('prospecting_public_research_records', [
            'prospecting_search_result_id' => $buyer->id,
            'status' => 'completed',
        ]);
        $this->assertSame(1, ProspectingPublicResearchRecord::query()->count());
        $this->assertDatabaseCount('prospecting_candidates', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_blocked_buyer_like_fetch_does_not_create_false_candidate_readiness(): void
    {
        [$actor, , $run, $job] = $this->activationCFixture([
            'completed',
            'page_dtd_blocked',
        ]);
        $completed = $job->searchResults()->where('rank', 1)->firstOrFail()->publicFetch;
        $completed->update([
            'page_title' => 'North public profile',
            'meta_description' => 'Public company profile.',
            'headings' => ['About'],
            'text_excerpt' => 'Bounded public profile evidence.',
        ]);
        $blockedBuyer = $job->searchResults()->where('rank', 2)->firstOrFail();
        $blockedBuyer->update([
            'title' => 'North Food Factory | About',
            'snippet' => 'Manufacturer of ready meals and frozen products.',
        ]);
        $job->searchResults()->where('rank', '>', 2)->update([
            'title' => 'Оптовый поставщик — купить оптом',
            'snippet' => 'Продажа оптом и прайс-лист.',
        ]);

        $waiting = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::RequiresAction, $waiting->status);
        $this->assertSame('public_research_review_required', $waiting->safe_error_code);
        $this->assertSame(1, ProspectingPublicResearchRecord::query()->where('status', 'completed')->count());
        $this->assertSame('blocked', $blockedBuyer->fresh()->publicFetch->status);
        $this->assertDatabaseCount('prospecting_candidates', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_manual_candidate_ingestion_resumes_exact_campaign_without_unit_entity_or_provider_calls(): void
    {
        [$actor, , $run, $job] = $this->activationCFixture(['completed']);
        config()->set('ai-sales.prospecting.candidate_import_enabled', true);
        $waiting = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);
        $this->assertSame('candidate_ingestion_review_required', $waiting->safe_error_code);
        $result = $job->searchResults()->where('rank', 1)->firstOrFail();
        $before = $this->sideEffectCounts($job);
        Queue::fake();

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/search-results/'.$result->public_id.'/ingest-candidate', [])
            ->assertCreated()
            ->assertJsonPath('campaign_resume_queued', true)
            ->assertJsonPath('unit_created', false)
            ->assertJsonPath('entity_created', false)
            ->assertJsonPath('entity_linked', false);
        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/search-results/'.$result->public_id.'/ingest-candidate', [])
            ->assertCreated()
            ->assertJsonPath('campaign_resume_queued', true);

        $this->assertSame($before['executions'], $job->searchExecutions()->count());
        $this->assertSame($before['requests'], (int) $job->searchExecutions()->sum('request_count'));
        $this->assertSame($before['results'], $job->searchResults()->count());
        $this->assertSame($before['units'], DB::table('units')->count());
        $this->assertSame($before['entities'], DB::table('entities')->count());
        $this->assertSame(1, $job->candidates()->count());
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, 1);
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, fn ($queued): bool => $queued->runId === $run->id
            && $queued->actorUserId === $run->initiator_user_id);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_candidate_ingested_from_public_research_review_resumes_without_more_fetches(): void
    {
        [$actor, , $run, $job] = $this->activationCFixture(['completed']);
        config()->set('ai-sales.prospecting.candidate_import_enabled', true);
        $result = $job->searchResults()->where('rank', 1)->firstOrFail();
        $fetchesBefore = ProspectingPublicFetch::query()->count();
        $unitsBefore = DB::table('units')->count();
        $entitiesBefore = DB::table('entities')->count();
        Queue::fake();

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/search-results/'.$result->public_id.'/ingest-candidate', [])
            ->assertCreated()
            ->assertJsonPath('campaign_resume_queued', true);
        $completed = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run->fresh(), $actor);

        $this->assertSame(AiRunStatus::Completed, $completed->status);
        $this->assertSame('new_unit_review', $job->candidates()->sole()->status->value);
        $this->assertSame($fetchesBefore, ProspectingPublicFetch::query()->count());
        $this->assertSame(0, ProspectingPublicResearchRecord::query()->count());
        $this->assertSame($unitsBefore, DB::table('units')->count());
        $this->assertSame($entitiesBefore, DB::table('entities')->count());
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, 1);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_manual_public_fetch_stops_at_current_campaign_budget_before_http(): void
    {
        [$actor, , , $job] = $this->activationCFixture([
            'completed',
            'page_dtd_blocked',
            'robots_unavailable',
        ]);
        $unfetched = $job->searchResults()->where('rank', 4)->firstOrFail();

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/search-results/'.$unfetched->public_id.'/fetch', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('research')
            ->assertJsonPath('errors.research.0', 'public_research_page_budget_exhausted');

        $this->assertDatabaseCount('prospecting_public_fetches', 3);
        $this->assertSame('not_requested', $unfetched->fresh()->fetch_status);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_ai_sales_review_ui_uses_only_protected_saved_result_actions(): void
    {
        $panel = file_get_contents(resource_path('js/Components/AiSales/CandidateIngestionReviewPanel.vue'));
        $page = file_get_contents(resource_path('js/Pages/Ameise/AiSales.vue'));

        $this->assertStringContainsString('/api/ai-sales/prospecting/search-results/', $panel);
        $this->assertStringContainsString('/fetch', $panel);
        $this->assertStringContainsString('/research', $panel);
        $this->assertStringContainsString('/ingest-candidate', $panel);
        $this->assertStringContainsString('CandidateIngestionReviewPanel', $page);
        $this->assertStringNotContainsString('/search-execute', $panel);
        $this->assertStringNotContainsString('v-model="query"', $panel);
        $this->assertStringNotContainsString('v-model="url"', $panel);
        $this->assertStringNotContainsString('v-model="provider"', $panel);
    }

    public function test_production_shaped_activation_c_resume_reuses_search_and_reaches_idempotent_ingestion_review(): void
    {
        [$actor, $campaign, $run, $job] = $this->activationCFixture([
            'completed',
            'completed',
            'page_dtd_blocked',
        ]);
        $before = $this->sideEffectCounts($job);

        $resumed = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::RequiresAction, $resumed->status);
        $this->assertSame('candidate_ingestion_review_required', $resumed->safe_error_code);
        $this->assertDatabaseHas('ai_agent_run_steps', [
            'ai_agent_run_id' => $run->id,
            'sequence' => 7,
            'status' => 'requires_action',
            'safe_error_code' => 'candidate_ingestion_review_required',
        ]);
        $this->assertDatabaseCount('prospecting_search_executions', 1);
        $this->assertDatabaseCount('prospecting_search_results', 10);
        $this->assertDatabaseCount('prospecting_public_fetches', 3);
        $this->assertDatabaseCount('prospecting_public_research_records', 2);
        $this->assertSame(2, ProspectingPublicResearchRecord::query()->where('status', 'completed')->count());
        $this->assertSame(1, ProspectingSearchExecution::query()->sum('request_count'));
        $this->assertSame($before, $this->sideEffectCounts($job));

        $reviews = collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor))
            ->where('category', 'candidate_ingestion_review')->values();
        $this->assertCount(1, $reviews);
        $this->assertSame($job->id, $reviews->first()['search_job_id']);

        $repeated = app(AdvanceClientAcquisitionCampaignRun::class)->handle($resumed, $actor);
        $this->assertSame($run->id, $repeated->id);
        $this->assertSame('candidate_ingestion_review_required', $repeated->safe_error_code);
        $this->assertDatabaseCount('prospecting_public_research_records', 2);
        $this->assertDatabaseCount('prospecting_public_fetches', 3);
        $this->assertDatabaseCount('prospecting_search_executions', 1);
        $this->assertSame(1, ProspectingSearchExecution::query()->sum('request_count'));
        $this->assertCount(1, collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor))
            ->where('category', 'candidate_ingestion_review'));
        $this->assertSame($before, $this->sideEffectCounts($job));
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_insufficient_existing_fetch_evidence_remains_in_code_owned_public_research_review(): void
    {
        [$actor, , $run, $job] = $this->activationCFixture([
            'page_dtd_blocked',
            'robots_unavailable',
            'body_too_large',
        ]);
        $before = $this->sideEffectCounts($job);

        $waiting = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::RequiresAction, $waiting->status);
        $this->assertSame('public_research_review_required', $waiting->safe_error_code);
        $this->assertDatabaseCount('prospecting_public_fetches', 3);
        $this->assertDatabaseCount('prospecting_public_research_records', 0);
        $this->assertDatabaseCount('prospecting_search_executions', 1);
        $this->assertSame(1, ProspectingSearchExecution::query()->sum('request_count'));
        $this->assertSame($before, $this->sideEffectCounts($job));
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @param list<string> $fetchOutcomes
     * @return array{User, ClientAcquisitionCampaign, AiAgentRun, ProspectingSearchJob}
     */
    private function activationCFixture(array $fetchOutcomes): array
    {
        $this->configureActivationC();
        $actor = $this->campaignUser();
        $product = $this->campaignProduct('Activation C Product');
        $campaign = $this->approvedCampaign($actor, $product, [
            'automation_mode' => 'assisted',
            'criteria' => [
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 3,
                'max_page_fetch_attempts' => 3,
                'max_results_per_query' => 10,
            ],
            'limits' => array_replace($this->campaignLimits(), [
                'max_active_runs' => 1,
                'max_runs_per_day' => 1,
                'max_runs_per_month' => 1,
                'max_search_requests_per_run' => 1,
                'max_search_requests_per_day' => 1,
                'max_search_requests_per_month' => 1,
                'max_research_pages_per_run' => 3,
                'max_candidates_per_run' => 5,
                'max_units_per_run' => 0,
                'max_units_per_day' => 0,
                'max_units_per_month' => 0,
                'max_drafts_per_run' => 0,
                'max_drafts_per_day' => 0,
                'max_drafts_per_month' => 0,
            ]),
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)
            ->handle($campaign, $actor, 'activation-c-production-shaped-resume');
        $job = app(ClientAcquisitionCampaignSearchJobService::class)->ensure($campaign, $run);
        app(PlanProspectingQueries::class)->handle($job, $actor);
        $query = app(ApproveProspectingQueryPlan::class)->handle($job, $actor)->sole();
        $execution = ProspectingSearchExecution::query()->create([
            'prospecting_search_job_id' => $job->id,
            'prospecting_search_query_id' => $query->id,
            'initiated_by' => $actor->id,
            'profile_code' => 'prospecting_b2b_discovery',
            'provider_code' => 'existing_yandex',
            'request_hash' => hash('sha256', 'activation-c-request'),
            'plan_hash' => $query->plan_hash,
            'status' => 'completed',
            'attempt' => 1,
            'request_count' => 1,
            'result_count' => 10,
            'duplicate_count' => 0,
            'blocked_result_count' => 0,
            'duration_ms' => 25,
            'safe_request_id' => 'activation-c-yandex-1',
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ]);
        ProspectingSearchUsageRecord::query()->create([
            'prospecting_search_execution_id' => $execution->id,
            'provider_code' => 'existing_yandex',
            'profile_code' => 'prospecting_b2b_discovery',
            'request_count' => 1,
            'result_count' => 10,
            'estimated_cost_rub' => 0,
            'safe_request_id' => 'activation-c-yandex-1',
            'recorded_at' => now(),
        ]);
        $query->update(['status' => 'completed', 'result_count' => 10]);

        foreach (range(1, 10) as $rank) {
            $domain = sprintf('buyer-%02d.example', $rank);
            $url = "https://{$domain}/about";
            $result = ProspectingSearchResult::query()->create([
                'prospecting_search_execution_id' => $execution->id,
                'prospecting_search_job_id' => $job->id,
                'prospecting_search_query_id' => $query->id,
                'rank' => $rank,
                'result_type' => 'organic',
                'title' => "Synthetic buyer {$rank}",
                'snippet' => 'Bounded fictional public evidence.',
                'url' => $url,
                'canonical_url' => $url,
                'url_hash' => hash('sha256', $url),
                'registrable_domain' => $domain,
                'domain_hash' => hash('sha256', $domain),
                'result_hash' => hash('sha256', "activation-c-result-{$rank}"),
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'fetch_status' => 'not_requested',
                'research_status' => 'not_requested',
            ]);
            if (isset($fetchOutcomes[$rank - 1])) {
                $this->createFetch($result, $fetchOutcomes[$rank - 1]);
            }
        }

        $run->steps()->where('sequence', '<', 6)->update([
            'status' => 'completed',
            'completed_at' => now(),
            'retry_count' => 0,
            'failover_count' => 0,
        ]);
        $run->steps()->where('sequence', 6)->update([
            'status' => 'requires_action',
            'safe_error_code' => 'public_research_review_required',
            'safe_error_summary' => 'Existing bounded fetch evidence requires research review.',
            'retry_count' => 0,
            'failover_count' => 0,
        ]);
        $run->update([
            'status' => 'requires_action',
            'current_step' => 6,
            'accumulated_searches' => 1,
            'safe_error_code' => 'public_research_review_required',
            'safe_error_summary' => 'Existing bounded fetch evidence requires research review.',
        ]);

        return [$actor, $campaign->fresh(), $run->fresh('steps'), $job->fresh()];
    }

    private function configureActivationC(): void
    {
        config()->set([
            'ai-sales.web_search_enabled' => true,
            'ai-sales.campaigns.live_search_enabled' => true,
            'ai-sales.campaigns.live_research_enabled' => true,
            'ai-sales.campaigns.auto_ingest_enabled' => false,
            'ai-sales.campaigns.auto_create_unit_enabled' => false,
            'ai-sales.campaigns.auto_scoring_enabled' => false,
            'ai-sales.campaigns.auto_draft_enabled' => false,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.public_research_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => false,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.limits.max_retries' => 0,
            'ai-sales.campaigns.limits.max_active_runs' => 1,
            'ai-sales.campaigns.limits.max_runs_per_day' => 1,
            'ai-sales.campaigns.limits.max_runs_per_month' => 1,
            'ai-sales.campaigns.limits.max_search_requests_per_run' => 1,
            'ai-sales.campaigns.limits.max_search_results_per_run' => 10,
            'ai-sales.campaigns.limits.max_research_pages_per_run' => 3,
            'ai-sales.campaigns.limits.max_domains_per_run' => 3,
            'ai-sales.campaigns.limits.max_candidates_per_run' => 5,
            'ai-sales.campaigns.limits.global_units_per_day' => 0,
            'ai-sales.campaigns.limits.global_units_per_month' => 0,
            'ai-sales.campaigns.limits.global_drafts_per_day' => 0,
            'ai-sales.campaigns.limits.global_drafts_per_month' => 0,
        ]);
    }

    private function createFetch(ProspectingSearchResult $result, string $outcome): ProspectingPublicFetch
    {
        if ($outcome === 'completed') {
            $fetch = $result->publicFetch()->create([
                'status' => 'completed',
                'final_url' => $result->canonical_url,
                'final_url_hash' => hash('sha256', $result->canonical_url),
                'registrable_domain' => $result->registrable_domain,
                'content_type' => 'text/html',
                'byte_count' => 128,
                'duration_ms' => 10,
                'page_title' => 'Synthetic Food Factory',
                'meta_description' => 'Fictional manufacturer of ready meals and frozen vegetable mixes.',
                'headings' => ['About the food factory'],
                'text_excerpt' => 'Public company evidence describes food production and a reviewed Product scope.',
                'same_domain_links' => [],
                'protected_channels' => [],
                'channel_count' => 0,
                'content_hash' => hash('sha256', 'activation-c-fetch-'.$result->rank),
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'robots_status' => 'unavailable',
                'fetched_at' => now(),
            ]);
            $result->update(['fetch_status' => 'completed']);

            return $fetch;
        }

        $fetch = $result->publicFetch()->create([
            'status' => 'blocked',
            'trust_level' => 'untrusted',
            'instruction_authority' => 'none',
            'error_category' => 'fetch_policy',
            'error_code' => $outcome,
            'fetched_at' => now(),
        ]);
        $result->update(['fetch_status' => 'blocked']);

        return $fetch;
    }

    /** @return array{executions: int, requests: int, results: int, fetches: int, candidates: int, units: int, entities: int, mail_attempts: int, dispatches: int} */
    private function sideEffectCounts(ProspectingSearchJob $job): array
    {
        return [
            'executions' => $job->searchExecutions()->count(),
            'requests' => (int) $job->searchExecutions()->sum('request_count'),
            'results' => $job->searchResults()->count(),
            'fetches' => ProspectingPublicFetch::query()->count(),
            'candidates' => $job->candidates()->count(),
            'units' => DB::table('units')->count(),
            'entities' => DB::table('entities')->count(),
            'mail_attempts' => DB::table('authorized_mail_dispatch_attempts')->count(),
            'dispatches' => DB::table('outreach_dispatches')->count(),
        ];
    }
}
