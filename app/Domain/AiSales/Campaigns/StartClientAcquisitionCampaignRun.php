<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StartClientAcquisitionCampaignRun
{
    public function __construct(
        private readonly ClientAcquisitionCampaignFeatureGuard $features,
        private readonly ClientAcquisitionCampaignAuthorizationService $authorization,
        private readonly ClientAcquisitionCampaignHashes $hashes,
        private readonly ClientAcquisitionCampaignBudgetGuard $budgets,
        private readonly ClientAcquisitionCampaignWorkflowRegistry $workflow,
        private readonly ClientAcquisitionCampaignStateMachine $states,
    ) {}

    public function handle(
        ClientAcquisitionCampaign $campaign,
        User $actor,
        string $requestKey,
        ?Carbon $scheduledFor = null,
    ): AiAgentRun {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::OPERATE);
        $key = hash('sha256', implode('|', [
            'campaign-run-v1', $campaign->id, $campaign->approval_snapshot_hash, $requestKey,
            $scheduledFor?->copy()->utc()->format('Y-m-d\TH:i:s\Z') ?? 'manual',
        ]));

        return Cache::lock('ai-sales:campaign:start:'.$campaign->id, 20)->block(5, function () use ($campaign, $actor, $key, $scheduledFor): AiAgentRun {
            $existing = ClientAcquisitionCampaignRunLink::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing->run()->firstOrFail();
            }
            $campaign = ClientAcquisitionCampaign::query()->findOrFail($campaign->id);
            if (! in_array($campaign->status, [
                ClientAcquisitionCampaignStatus::Approved,
                ClientAcquisitionCampaignStatus::Scheduled,
                ClientAcquisitionCampaignStatus::Running,
            ], true)) {
                throw new PolicyViolation('campaign_not_runnable', 'Campaign is not in a runnable state.');
            }
            if (! $this->hashes->isCurrent($campaign)) {
                throw new PolicyViolation('campaign_approval_stale', 'Campaign approval hash is stale.');
            }
            $active = $campaign->runLinks()->whereHas('run', fn ($query) => $query->whereIn('status', [
                'queued', 'preparing', 'policy_check', 'ready', 'sent', 'requires_action', 'processing',
            ]))->latest('id')->first();
            if ($active) {
                return $active->run()->firstOrFail();
            }
            $this->budgets->assertCanStart($campaign);

            return DB::transaction(function () use ($campaign, $actor, $key, $scheduledFor): AiAgentRun {
                $locked = ClientAcquisitionCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
                $run = AiAgentRun::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'definition_code' => ClientAcquisitionCampaignWorkflowRegistry::CODE,
                    'definition_version' => ClientAcquisitionCampaignWorkflowRegistry::VERSION,
                    'initiator_user_id' => $actor->id,
                    'unit_name_snapshot' => 'Server-owned Product-first campaign scope',
                    'unit_context_snapshot' => [
                        'campaign_public_id' => $locked->public_id,
                        'purpose' => 'buyer_discovery',
                        'lane' => 'sales',
                        'role_code' => 'prospective_customer',
                    ],
                    'purpose' => AiPurpose::BuyerDiscovery,
                    'audience' => AiAudience::ProspectiveCustomer,
                    'lane' => BusinessLane::Sales,
                    'role_code' => UnitRoleCode::ProspectiveCustomer,
                    'task_profile' => AiTaskProfile::PublicCompanyResearch,
                    'requested_contour' => AiProcessingContour::None,
                    'selected_contour' => AiProcessingContour::None,
                    'model_profile_preference' => AiModelProfile::StandardResearch,
                    'status' => AiRunStatus::Queued,
                    'policy_decision_hash' => $locked->approval_snapshot_hash,
                    'prompt_hash' => $locked->workflow_hash,
                    'schema_hash' => $locked->workflow_hash,
                    'safe_input_summary' => 'Approved Product-first buyer-acquisition campaign.',
                    'safe_input_hash' => $locked->approval_snapshot_hash,
                    'max_steps' => count($this->workflow->stages()),
                    'max_searches' => $locked->max_search_requests_per_run,
                    'max_tokens' => $locked->max_tokens_per_run,
                    'max_cost_rub' => $locked->max_cost_rub_per_run,
                    'current_step' => 1,
                    'idempotency_key' => $key,
                    'correlation_id' => (string) Str::uuid(),
                    'queued_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);
                foreach ($this->workflow->stages() as $stage) {
                    AiAgentRunStep::query()->create([
                        'ai_agent_run_id' => $run->id,
                        'sequence' => $stage['sequence'],
                        'step_type' => $stage['code'],
                        'contour' => AiProcessingContour::None,
                        'sanitized_input_hash' => hash('sha256', $run->public_id.'|'.$stage['code']),
                        'safe_request_summary' => $stage['label'],
                        'status' => $stage['sequence'] === 1 ? AiRunStepStatus::Ready : AiRunStepStatus::Queued,
                        'retry_count' => 0,
                        'failover_count' => 0,
                    ]);
                }
                ClientAcquisitionCampaignRunLink::query()->create([
                    'ai_sales_campaign_id' => $locked->id,
                    'ai_agent_run_id' => $run->id,
                    'approval_snapshot_hash' => $locked->approval_snapshot_hash,
                    'idempotency_key' => $key,
                    'scheduled_for' => $scheduledFor,
                ]);
                if ($locked->status !== ClientAcquisitionCampaignStatus::Running) {
                    $this->states->transition($locked, ClientAcquisitionCampaignStatus::Running, [
                        'last_run_at' => now(),
                        'safe_status_summary' => 'A bounded campaign run is active.',
                    ]);
                }

                return $run->fresh('steps');
            }, 3);
        });
    }
}
