<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\AdvanceClientAcquisitionCampaignRun;
use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignSearchJobService;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Prospecting\ProspectingResearchBudget;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class Stage14ActivationReadinessTest extends Stage14TestCase
{
    public function test_missing_campaign_ceiling_blocks(): void
    {
        config()->set('ai-sales.campaigns.limits.max_search_results_per_run', null);

        $this->assertCampaignStartBlocked('campaign_budget_missing');
    }

    public function test_zero_campaign_ceiling_blocks(): void
    {
        config()->set('ai-sales.campaigns.limits.max_domains_per_run', 0);

        $this->assertCampaignStartBlocked('campaign_budget_missing');
    }

    public function test_owner_configured_bounded_ceiling_permits_one_manual_run(): void
    {
        $this->configureBoundedCeilings();
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor, null, $this->boundedCampaignOverrides());

        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'bounded-manual-run');

        $this->assertSame(AiRunStatus::Queued, $run->status);
        $this->assertSame(1, $run->max_searches);
        $this->assertDatabaseCount('ai_sales_campaign_run_links', 1);
        $this->assertDatabaseHas('ai_sales_campaigns', [
            'id' => $campaign->id,
            'automation_mode' => 'manual',
            'max_active_runs' => 1,
            'max_search_requests_per_run' => 1,
            'max_candidates_per_run' => 5,
            'max_units_per_run' => 0,
            'max_drafts_per_run' => 0,
        ]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_attempted_value_above_global_ceiling_blocks(): void
    {
        $this->configureBoundedCeilings();
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor, null, $this->boundedCampaignOverrides([
            'max_search_requests_per_run' => 2,
        ]));

        try {
            app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'above-global-ceiling');
            $this->fail('A campaign value above the global ceiling must block.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('campaign_budget_exceeds_global', $exception->errorCode);
        }

        $this->assertDatabaseCount('ai_sales_campaign_run_links', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_manual_mode_with_auto_ingest_disabled_requires_idempotent_review(): void
    {
        $this->assertAssistedIngestionPause('manual');
    }

    public function test_assisted_mode_with_auto_ingest_disabled_requires_idempotent_review(): void
    {
        $this->assertAssistedIngestionPause('assisted');
    }

    public function test_autonomous_reviewed_with_auto_ingest_disabled_is_blocked(): void
    {
        [$actor, $campaign, $run] = $this->ingestionStageFixture('autonomous_reviewed');
        config()->set('ai-sales.campaigns.auto_ingest_enabled', false);

        $blocked = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::BlockedByPolicy, $blocked->status);
        $this->assertSame('campaign_auto_ingest_disabled', $blocked->safe_error_code);
        $this->assertSame('blocked', $campaign->fresh()->status->value);
        $this->assertDatabaseHas('ai_agent_run_steps', [
            'ai_agent_run_id' => $run->id,
            'sequence' => 7,
            'status' => 'blocked',
            'safe_error_code' => 'campaign_auto_ingest_disabled',
        ]);
        $this->assertNoIngestionSideEffects();
    }

    public function test_autonomous_reviewed_auto_ingest_still_requires_all_existing_policy_guards(): void
    {
        [$actor, $campaign, $run] = $this->ingestionStageFixture('autonomous_reviewed');
        config()->set([
            'ai-sales.campaigns.auto_ingest_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => false,
        ]);

        $blocked = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::BlockedByPolicy, $blocked->status);
        $this->assertSame('campaign_candidate_ingestion_policy_blocked', $blocked->safe_error_code);
        $this->assertSame('blocked', $campaign->fresh()->status->value);
        $ingestion = file_get_contents(app_path('Domain/AiSales/Services/IngestProspectingSearchCandidate.php'));
        $candidatePersistence = file_get_contents(app_path('Domain/AiSales/Services/ProspectingCandidateService.php'));
        $normalizer = file_get_contents(app_path('Domain/AiSales/Services/ProspectingCandidateNormalizer.php'));
        $this->assertStringContainsString('candidateImport()', $ingestion);
        $this->assertStringContainsString('duplicate_of_id', $ingestion);
        $this->assertStringContainsString('registrable_domain', $ingestion);
        $this->assertStringContainsString('createFromSearchResult', $ingestion);
        $this->assertStringContainsString('ProspectingJobStatus::Approved', $candidatePersistence);
        $this->assertStringContainsString('firstOrCreate', $candidatePersistence);
        $this->assertStringContainsString('assertPayloadSafe', $normalizer);
        $this->assertNoIngestionSideEffects();
    }

    public function test_campaign_ceiling_defaults_and_example_values_remain_zero(): void
    {
        $config = require config_path('ai-sales.php');
        $expected = [
            'scheduler_batch', 'max_active_runs', 'max_runs_per_day', 'max_runs_per_month',
            'max_search_requests_per_run', 'max_search_results_per_run', 'max_research_pages_per_run',
            'max_domains_per_run', 'max_candidates_per_run', 'global_units_per_day',
            'global_units_per_month', 'global_drafts_per_day', 'global_drafts_per_month',
        ];
        $this->assertSame($expected, array_keys($config['campaigns']['limits']));
        foreach ($expected as $key) {
            $this->assertSame(0, $config['campaigns']['limits'][$key], $key);
        }

        $example = file_get_contents(base_path('.env.example'));
        foreach ([
            'AI_SALES_CAMPAIGN_SCHEDULER_BATCH', 'AI_SALES_CAMPAIGN_MAX_ACTIVE_RUNS',
            'AI_SALES_CAMPAIGN_MAX_RUNS_PER_DAY', 'AI_SALES_CAMPAIGN_MAX_RUNS_PER_MONTH',
            'AI_SALES_CAMPAIGN_MAX_SEARCH_REQUESTS_PER_RUN', 'AI_SALES_CAMPAIGN_MAX_SEARCH_RESULTS_PER_RUN',
            'AI_SALES_CAMPAIGN_MAX_RESEARCH_PAGES_PER_RUN', 'AI_SALES_CAMPAIGN_MAX_DOMAINS_PER_RUN',
            'AI_SALES_CAMPAIGN_MAX_CANDIDATES_PER_RUN', 'AI_SALES_CAMPAIGN_GLOBAL_UNITS_PER_DAY',
            'AI_SALES_CAMPAIGN_GLOBAL_UNITS_PER_MONTH', 'AI_SALES_CAMPAIGN_GLOBAL_DRAFTS_PER_DAY',
            'AI_SALES_CAMPAIGN_GLOBAL_DRAFTS_PER_MONTH',
        ] as $name) {
            $this->assertStringContainsString("{$name}=0", $example);
        }
    }

    public function test_campaign_owned_research_limits_are_not_clamped_by_browser_draft_limits(): void
    {
        config()->set([
            'ai-sales.campaigns.limits.max_research_pages_per_run' => 30,
            'ai-sales.campaigns.limits.max_domains_per_run' => 30,
            'ai-sales.find_buyers.limits.max_page_fetch_attempts' => 5,
            'ai-sales.find_buyers.limits.max_domains' => 10,
        ]);
        $actor = $this->campaignUser();
        $product = $this->campaignProduct('Campaign research ceilings');
        $campaign = $this->approvedCampaign($actor, $product, [
            'criteria' => [
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 30,
                'max_page_fetch_attempts' => 30,
                'max_results_per_query' => 10,
            ],
            'limits' => array_replace($this->campaignLimits(), [
                'max_research_pages_per_run' => 30,
            ]),
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)
            ->handle($campaign, $actor, 'campaign-owned-research-limits');
        $campaignJob = app(ClientAcquisitionCampaignSearchJobService::class)->ensure($campaign, $run);

        $this->assertSame(30, $campaignJob->criteria_snapshot['max_domains']);
        $this->assertSame(30, $campaignJob->criteria_snapshot['max_page_fetch_attempts']);

        $browserJob = app(ProspectingSearchJobService::class)->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Bounded browser-owned draft remains under Find Buyers limits.',
            'primary_product_id' => $product->id,
            'criteria' => [
                'segments' => ['synthetic'],
                'max_domains' => 30,
                'max_page_fetch_attempts' => 30,
            ],
        ], $actor);
        $this->assertSame(10, $browserJob->criteria_snapshot['max_domains']);
        $this->assertSame(5, $browserJob->criteria_snapshot['max_page_fetch_attempts']);

        // Existing production jobs keep their immutable snapshot, while the current
        // approved Campaign remains the source of truth for its reviewed run budget.
        $campaignJob->update(['criteria_snapshot' => array_replace($campaignJob->criteria_snapshot, [
            'max_domains' => 10,
            'max_page_fetch_attempts' => 5,
        ])]);
        $budget = app(ProspectingResearchBudget::class)->snapshot($campaignJob->fresh());
        $this->assertSame('campaign', $budget['source']);
        $this->assertTrue($budget['current']);
        $this->assertSame(30, $budget['domains_limit']);
        $this->assertSame(30, $budget['pages_limit']);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    private function assertCampaignStartBlocked(string $expectedCode): void
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);

        try {
            app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'missing-or-zero-ceiling');
            $this->fail('A missing or zero global campaign ceiling must block.');
        } catch (PolicyViolation $exception) {
            $this->assertSame($expectedCode, $exception->errorCode);
        }

        $this->assertDatabaseCount('ai_sales_campaign_run_links', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    private function configureBoundedCeilings(): void
    {
        config()->set([
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

    private function boundedCampaignOverrides(array $limitOverrides = []): array
    {
        return [
            'criteria' => [
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 3,
                'max_page_fetch_attempts' => 3,
                'max_results_per_query' => 10,
            ],
            'limits' => [
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
                ...$limitOverrides,
            ],
        ];
    }

    private function assertAssistedIngestionPause(string $mode): void
    {
        [$actor, $campaign, $run, $job] = $this->ingestionStageFixture($mode);
        config()->set('ai-sales.campaigns.auto_ingest_enabled', false);
        $before = $this->ingestionSideEffectCounts();

        $waiting = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::RequiresAction, $waiting->status);
        $this->assertSame('candidate_ingestion_review_required', $waiting->safe_error_code);
        $this->assertSame('running', $campaign->fresh()->status->value);
        $this->assertDatabaseHas('ai_agent_run_steps', [
            'ai_agent_run_id' => $run->id,
            'sequence' => 7,
            'status' => 'requires_action',
            'safe_error_code' => 'candidate_ingestion_review_required',
        ]);

        $reviews = collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor))
            ->where('category', 'candidate_ingestion_review')->values();
        $this->assertCount(1, $reviews);
        $this->assertSame($job->id, $reviews->first()['search_job_id']);
        $this->assertSame($run->public_id, $reviews->first()['run_id']);

        $repeated = app(AdvanceClientAcquisitionCampaignRun::class)->handle($waiting, $actor);
        $this->assertSame($run->id, $repeated->id);
        $this->assertSame(AiRunStatus::RequiresAction, $repeated->status);
        $this->assertCount(1, collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor))
            ->where('category', 'candidate_ingestion_review'));
        $sameActiveRun = app(StartClientAcquisitionCampaignRun::class)
            ->handle($campaign->fresh(), $actor, 'repeated-worker-or-scheduler-tick');
        $this->assertSame($run->id, $sameActiveRun->id);
        $this->assertDatabaseCount('ai_sales_campaign_run_links', 1);
        $this->assertSame($before, $this->ingestionSideEffectCounts());
        $this->assertNoIngestionSideEffects();
    }

    /** @return array{User, ClientAcquisitionCampaign, \App\Models\AiAgentRun, ProspectingSearchJob} */
    private function ingestionStageFixture(string $mode): array
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor, null, ['automation_mode' => $mode]);
        $run = app(StartClientAcquisitionCampaignRun::class)
            ->handle($campaign, $actor, 'ingestion-stage-'.$mode);
        $job = app(ClientAcquisitionCampaignSearchJobService::class)->ensure($campaign, $run);
        $run->steps()->where('sequence', '<', 7)->update([
            'status' => 'completed',
            'completed_at' => now(),
            'retry_count' => 0,
            'failover_count' => 0,
        ]);
        $run->steps()->where('sequence', 7)->update(['status' => 'ready']);
        $run->update(['status' => 'ready', 'current_step' => 7]);

        return [$actor, $campaign->fresh(), $run->fresh('steps'), $job->fresh()];
    }

    /** @return array{candidates: int, units: int, entities: int} */
    private function ingestionSideEffectCounts(): array
    {
        return [
            'candidates' => DB::table('prospecting_candidates')->count(),
            'units' => DB::table('units')->count(),
            'entities' => DB::table('entities')->count(),
        ];
    }

    private function assertNoIngestionSideEffects(): void
    {
        $this->assertDatabaseCount('prospecting_candidates', 0);
        $this->assertSame(0, DB::table('entities')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
