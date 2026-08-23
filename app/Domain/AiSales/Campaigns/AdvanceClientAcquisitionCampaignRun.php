<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignCadence;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdvanceClientAcquisitionCampaignRun
{
    public function __construct(
        private readonly ClientAcquisitionCampaignFeatureGuard $features,
        private readonly ClientAcquisitionCampaignAuthorizationService $authorization,
        private readonly ClientAcquisitionCampaignHashes $hashes,
        private readonly ClientAcquisitionCampaignBudgetGuard $budgets,
        private readonly ClientAcquisitionCampaignStateMachine $states,
        private readonly ClientAcquisitionCampaignService $campaigns,
        private readonly ClientAcquisitionCampaignStageProcessorInterface $processor,
    ) {}

    public function handle(AiAgentRun $run, User $actor): AiAgentRun
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::OPERATE);

        return Cache::lock('ai-sales:campaign:run:'.$run->id, 120)->block(5, function () use ($run, $actor): AiAgentRun {
            $link = ClientAcquisitionCampaignRunLink::query()->where('ai_agent_run_id', $run->id)->firstOrFail();
            $campaign = ClientAcquisitionCampaign::query()->findOrFail($link->ai_sales_campaign_id);
            $run = AiAgentRun::query()->findOrFail($run->id);
            if ($run->status->terminal()) {
                return $run->fresh('steps');
            }
            if ($campaign->status === ClientAcquisitionCampaignStatus::Cancelled) {
                return $this->cancelRun($run);
            }
            if ($campaign->status === ClientAcquisitionCampaignStatus::Paused) {
                return $run->fresh('steps');
            }
            if (! hash_equals((string) $campaign->approval_snapshot_hash, (string) $link->approval_snapshot_hash)
                || ! $this->hashes->isCurrent($campaign)) {
                return $this->block($campaign, $run, null, 'campaign_approval_stale', 'Campaign approval hash is stale.');
            }

            try {
                $this->budgets->assertRunWithinBudget($campaign, $run);
                $run->update([
                    'status' => AiRunStatus::Processing,
                    'started_at' => $run->started_at ?: now(),
                    'safe_error_code' => null,
                    'safe_error_summary' => null,
                ]);
                for ($guard = 0; $guard < 14; $guard++) {
                    $campaign->refresh();
                    if ($campaign->status === ClientAcquisitionCampaignStatus::Cancelled) {
                        return $this->cancelRun($run->fresh());
                    }
                    if ($campaign->status === ClientAcquisitionCampaignStatus::Paused) {
                        return $run->fresh('steps');
                    }
                    $step = $run->steps()->whereNotIn('status', [
                        AiRunStepStatus::Completed->value, AiRunStepStatus::Cancelled->value,
                    ])->orderBy('sequence')->first();
                    if (! $step) {
                        return $this->complete($campaign, $run->fresh());
                    }
                    $step->update([
                        'status' => AiRunStepStatus::Processing,
                        'started_at' => $step->started_at ?: now(),
                        'safe_error_code' => null,
                        'safe_error_summary' => null,
                    ]);
                    $outcome = $this->processor->process($campaign, $run->fresh(), $step->fresh(), $actor);
                    if ($outcome->kind === 'completed') {
                        $step->update([
                            'status' => AiRunStepStatus::Completed,
                            'normalized_output_metadata' => $outcome->metadata,
                            'completed_at' => now(),
                            'retry_count' => 0,
                            'failover_count' => 0,
                        ]);
                        $run->update(['current_step' => min(14, $step->sequence + 1)]);
                        if ((int) $step->sequence === 14
                            || ! $run->steps()->whereNotIn('status', [
                                AiRunStepStatus::Completed->value, AiRunStepStatus::Cancelled->value,
                            ])->exists()) {
                            return $this->complete($campaign->fresh(), $run->fresh());
                        }

                        continue;
                    }
                    if (in_array($outcome->kind, ['pending', 'requires_action'], true)) {
                        $step->update([
                            'status' => AiRunStepStatus::RequiresAction,
                            'normalized_output_metadata' => $outcome->metadata,
                            'safe_error_code' => $outcome->safeCode,
                            'safe_error_summary' => $outcome->safeSummary,
                            'retry_count' => 0,
                            'failover_count' => 0,
                        ]);
                        $run->update([
                            'status' => AiRunStatus::RequiresAction,
                            'safe_error_code' => $outcome->safeCode,
                            'safe_error_summary' => $outcome->safeSummary,
                        ]);
                        $campaign->update(['safe_status_summary' => $outcome->safeSummary]);

                        return $run->fresh('steps');
                    }

                    return $this->block($campaign, $run, $step, $outcome->safeCode, $outcome->safeSummary, $outcome->metadata);
                }

                return $this->block($campaign, $run, null, 'campaign_stage_guard_exceeded', 'Campaign stage bound was exceeded.');
            } catch (PolicyViolation $exception) {
                return $this->block($campaign, $run, null, $exception->errorCode, 'Campaign policy blocked the current run.');
            } catch (Throwable) {
                return $this->block($campaign, $run, null, 'campaign_processing_failed_safe', 'Campaign processing failed safely.');
            }
        });
    }

    private function block(
        ClientAcquisitionCampaign $campaign,
        AiAgentRun $run,
        ?AiAgentRunStep $step,
        string $code,
        string $summary,
        array $metadata = [],
    ): AiAgentRun {
        DB::transaction(function () use ($campaign, $run, $step, $code, $summary, $metadata): void {
            $step?->update([
                'status' => AiRunStepStatus::Blocked,
                'normalized_output_metadata' => $metadata,
                'safe_error_code' => $code,
                'safe_error_summary' => $summary,
                'completed_at' => now(),
                'retry_count' => 0,
                'failover_count' => 0,
            ]);
            $run->update([
                'status' => str_contains($code, 'budget') ? AiRunStatus::BudgetExceeded : AiRunStatus::BlockedByPolicy,
                'safe_error_code' => $code,
                'safe_error_summary' => $summary,
                'completed_at' => now(),
            ]);
            if ($campaign->status === ClientAcquisitionCampaignStatus::Running) {
                $this->states->transition($campaign->fresh(), ClientAcquisitionCampaignStatus::Blocked, [
                    'last_block_code' => $code,
                    'safe_status_summary' => $summary,
                ]);
            }
        }, 3);

        return $run->fresh('steps');
    }

    private function complete(ClientAcquisitionCampaign $campaign, AiAgentRun $run): AiAgentRun
    {
        DB::transaction(function () use ($campaign, $run): void {
            $run->update([
                'status' => AiRunStatus::Completed,
                'current_step' => 14,
                'completed_at' => now(),
                'safe_error_code' => null,
                'safe_error_summary' => null,
            ]);
            $campaign = $this->states->transition($campaign->fresh(), ClientAcquisitionCampaignStatus::Completed, [
                'completed_at' => now(),
                'last_run_at' => now(),
                'safe_status_summary' => 'Campaign run completed; review boundaries remain active.',
            ]);
            if ($campaign->schedule_cadence !== ClientAcquisitionCampaignCadence::Manual) {
                $this->states->transition($campaign, ClientAcquisitionCampaignStatus::Scheduled, [
                    'completed_at' => null,
                    'next_run_at' => $this->campaigns->nextRunAt($campaign),
                    'safe_status_summary' => 'Campaign run completed and the next bounded run is scheduled.',
                ]);
            }
        }, 3);

        return $run->fresh('steps');
    }

    private function cancelRun(AiAgentRun $run): AiAgentRun
    {
        $run->steps()->whereIn('status', ['queued', 'ready', 'sent', 'processing', 'requires_action'])->update([
            'status' => AiRunStepStatus::Cancelled->value,
            'safe_error_code' => 'campaign_cancelled',
            'safe_error_summary' => 'Campaign stage was cancelled.',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
        $run->update([
            'status' => AiRunStatus::Cancelled,
            'safe_error_code' => 'campaign_cancelled',
            'safe_error_summary' => 'Campaign run was cancelled.',
            'cancelled_at' => now(),
        ]);

        return $run->fresh('steps');
    }
}
