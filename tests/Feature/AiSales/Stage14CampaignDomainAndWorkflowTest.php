<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\AdvanceClientAcquisitionCampaignRun;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignFeatureGuard;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignService;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignStageOutcome;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignWorkflowRegistry;
use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\User;
use Illuminate\Support\Carbon;

class Stage14CampaignDomainAndWorkflowTest extends Stage14TestCase
{
    public function test_campaign_derives_fixed_context_and_material_edit_invalidates_approval(): void
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);

        $this->assertSame('buyer_discovery', $campaign->purpose->value);
        $this->assertSame('sales', $campaign->lane->value);
        $this->assertSame('prospective_customer', $campaign->role_code->value);
        $this->assertSame('approved', $campaign->status->value);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $campaign->approval_snapshot_hash);
        $this->assertSame(['primary'], $campaign->products->pluck('pivot.role')->all());

        $updated = app(ClientAcquisitionCampaignService::class)->update($campaign, [
            'safe_objective' => 'A material, bounded and human-authored change.',
        ], $actor);

        $this->assertSame('review_required', $updated->status->value);
        $this->assertNull($updated->approval_snapshot_hash);
        $this->assertNull($updated->approved_at);
    }

    public function test_schedule_pause_resume_cancel_and_due_logic_are_deterministic(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00 Europe/Moscow');
        try {
            $actor = $this->campaignUser();
            $campaign = $this->approvedCampaign($actor, null, [
                'schedule_cadence' => 'daily',
                'next_run_at' => now()->addHour(),
            ]);
            $service = app(ClientAcquisitionCampaignService::class);

            $this->assertSame('scheduled', $campaign->status->value);
            $this->assertSame('2026-08-20 11:00:00', $service->nextRunAt($campaign)->format('Y-m-d H:i:s'));
            $campaign = $service->pause($campaign, $actor);
            $this->assertSame('paused', $campaign->status->value);
            $campaign = $service->resume($campaign, $actor);
            $this->assertSame('scheduled', $campaign->status->value);
            $campaign = $service->cancel($campaign, $actor);
            $this->assertSame('cancelled', $campaign->status->value);
            $this->assertNull($campaign->next_run_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_run_is_idempotent_fixed_order_ids_only_and_has_no_retry_or_failover(): void
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);
        $starter = app(StartClientAcquisitionCampaignRun::class);
        $run = $starter->handle($campaign, $actor, 'stage14-idempotency-token');
        $replay = $starter->handle($campaign->fresh(), $actor, 'stage14-idempotency-token');

        $this->assertSame($run->id, $replay->id);
        $this->assertDatabaseCount('ai_sales_campaign_run_links', 1);
        $expected = collect(app(ClientAcquisitionCampaignWorkflowRegistry::class)->stages())->pluck('code')->all();
        $this->assertSame($expected, $run->steps()->orderBy('sequence')->pluck('step_type')->all());
        $this->assertSame([0], $run->steps()->distinct()->pluck('retry_count')->all());
        $this->assertSame([0], $run->steps()->distinct()->pluck('failover_count')->all());

        $job = new ExecuteClientAcquisitionCampaignRunJob($run->id, $actor->id);
        $this->assertSame(1, $job->tries);
        $this->assertSame($run->id, $job->runId);
        $this->assertSame($actor->id, $job->actorUserId);
        $this->assertFalse(collect(get_object_vars($job))->contains(
            fn ($value) => $value instanceof ClientAcquisitionCampaign || $value instanceof AiAgentRun,
        ));

        app()->instance(ClientAcquisitionCampaignStageProcessorInterface::class, new class implements ClientAcquisitionCampaignStageProcessorInterface
        {
            public function process(ClientAcquisitionCampaign $campaign, AiAgentRun $run, AiAgentRunStep $step, User $actor): ClientAcquisitionCampaignStageOutcome
            {
                return ClientAcquisitionCampaignStageOutcome::completed(['sequence' => $step->sequence]);
            }
        });
        $completed = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);

        $this->assertSame(AiRunStatus::Completed, $completed->status);
        $this->assertSame(14, $completed->steps()->where('status', 'completed')->count());
        $this->assertSame('completed', $campaign->fresh()->status->value);
    }

    public function test_requires_action_is_resumable_and_cancel_propagates_to_run_steps(): void
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'stage14-resume-token');
        $processor = new class implements ClientAcquisitionCampaignStageProcessorInterface
        {
            private bool $blockedOnce = false;

            public function process(ClientAcquisitionCampaign $campaign, AiAgentRun $run, AiAgentRunStep $step, User $actor): ClientAcquisitionCampaignStageOutcome
            {
                if ($step->sequence === 3 && ! $this->blockedOnce) {
                    $this->blockedOnce = true;

                    return ClientAcquisitionCampaignStageOutcome::requiresAction(
                        'query_plan_review_required',
                        'Human query-plan review is required.',
                    );
                }

                return ClientAcquisitionCampaignStageOutcome::completed();
            }
        };
        app()->instance(ClientAcquisitionCampaignStageProcessorInterface::class, $processor);
        $advance = app(AdvanceClientAcquisitionCampaignRun::class);

        $waiting = $advance->handle($run, $actor);
        $this->assertSame(AiRunStatus::RequiresAction, $waiting->status);
        $this->assertSame(2, $waiting->steps()->where('status', 'completed')->count());
        $completed = $advance->handle($waiting, $actor);
        $this->assertSame(AiRunStatus::Completed, $completed->status);

        $second = $this->approvedCampaign($actor);
        $active = app(StartClientAcquisitionCampaignRun::class)->handle($second, $actor, 'stage14-cancel-token');
        $service = app(ClientAcquisitionCampaignService::class);
        $paused = $service->pause($second->fresh(), $actor);
        $this->assertSame('paused', $paused->status->value);
        $resumed = $service->resume($paused, $actor);
        $this->assertSame('running', $resumed->status->value);
        $service->cancel($resumed, $actor);
        $this->assertSame(AiRunStatus::Cancelled, $active->fresh()->status);
        $this->assertSame(14, $active->steps()->where('status', 'cancelled')->count());
    }

    public function test_missing_global_budget_and_production_scheduler_apply_fail_closed(): void
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);
        config()->set('ai-sales.campaigns.limits.max_runs_per_day', 0);

        try {
            app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'blocked-budget');
            $this->fail('Zero global budget must block campaign start.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('campaign_budget_missing', $exception->errorCode);
        }

        config()->set('ai-sales.campaigns.scheduler_enabled', true);
        $this->app->detectEnvironment(fn () => 'production');
        try {
            app(ClientAcquisitionCampaignFeatureGuard::class)->scheduler();
            $this->fail('Production campaign scheduler must remain blocked.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('campaign_scheduler_production_blocked', $exception->errorCode);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
