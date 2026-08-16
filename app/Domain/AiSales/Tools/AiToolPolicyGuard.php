<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Runs\AiRunPolicyGuard;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\AiPolicyDecisionRecord;

class AiToolPolicyGuard
{
    public function __construct(private readonly AiRunPolicyGuard $runs) {}

    public function authorize(
        AiToolDefinition $tool,
        AiToolExecutionContext $execution,
        AiAgentRun $run,
        AiAgentRunStep $step,
    ): void {
        if (! (bool) config('ai-sales.tools.enabled', false)
            || ! (bool) config('ai-sales.workflows.enabled', false)) {
            throw new PolicyViolation('ai_tools_disabled', 'AI tools and workflows are disabled by default.');
        }

        if ((bool) config('ai-sales.provider_native_tools_enabled', false)) {
            throw new PolicyViolation('provider_native_tools_blocked', 'Provider-native dynamic tools are disabled on Stage 07.');
        }

        if (! $execution->syntheticOnly && ! (bool) config('ai-sales.live_business_workflows_enabled', false)) {
            throw new PolicyViolation('live_business_workflows_disabled', 'Live business workflows are disabled.');
        }

        if ($execution->syntheticOnly && ! app()->environment(['local', 'testing'])) {
            throw new PolicyViolation('synthetic_workflow_environment_blocked', 'Synthetic workflows are restricted to local and test environments.');
        }

        if (! $tool->enabled || $tool->sideEffectClass !== 'read_only' || $tool->humanReviewRequired) {
            throw new PolicyViolation('tool_disabled', 'The code-owned tool is disabled or requires human review.');
        }

        if ($tool->syntheticOnly !== $execution->syntheticOnly) {
            throw new PolicyViolation('tool_synthetic_binding_mismatch', 'Synthetic tool binding does not match the workflow.');
        }

        if ($run->id !== $execution->runId
            || $step->id !== $execution->runStepId
            || $step->ai_agent_run_id !== $run->id
            || $run->initiator_user_id !== $execution->actorUserId
            || $run->unit_id !== $execution->unitId
            || $run->unit_business_context_id !== $execution->unitBusinessContextId
            || $run->lane !== $execution->lane
            || $run->role_code !== $execution->role
            || $run->purpose !== $execution->purpose
            || $run->audience !== $execution->audience
            || $run->selected_contour !== $execution->contour
            || $step->contour !== $execution->contour
            || $run->lock_version !== $execution->expectedRunLockVersion) {
            throw new PolicyViolation('tool_execution_binding_mismatch', 'Tool execution bindings do not match the current run and step.');
        }

        if (! in_array($execution->purpose, $tool->allowedPurposes, true)
            || ! in_array($execution->audience, $tool->allowedAudiences, true)
            || ! in_array($execution->lane, $tool->allowedLanes, true)
            || ! in_array($execution->role, $tool->allowedRoles, true)
            || ! in_array($execution->contour, $tool->allowedContours, true)) {
            throw new PolicyViolation('tool_policy_dimension_blocked', 'Purpose, audience, lane, role or contour is not allowlisted for the tool.');
        }

        if (! $execution->role->allowsLane($execution->lane)) {
            throw new PolicyViolation('tool_role_lane_mismatch', 'Tool role and lane binding is invalid.');
        }

        if ($execution->reservedRows > $tool->maxRows
            || $execution->reservedBytes > $tool->maxBytes
            || $execution->reservedDurationMs > $tool->maxDurationMs
            || $this->moneyUnits($execution->reservedCostRub) > $this->moneyUnits($tool->maxCostRub)) {
            throw new PolicyViolation('tool_budget_reservation_invalid', 'Tool budget reservation exceeds its code-owned bounds.');
        }

        $record = AiPolicyDecisionRecord::query()
            ->whereKey($execution->policyDecisionId)
            ->where('ai_agent_run_id', $run->id)
            ->where('ai_agent_run_step_id', $step->id)
            ->where('decision_hash', $execution->policyDecisionHash)
            ->whereIn('decision', ['allow', 'redact'])
            ->first();

        if (! $record || ! hash_equals((string) $run->policy_decision_hash, $execution->policyDecisionHash)) {
            throw new PolicyViolation('tool_policy_decision_stale', 'Tool policy decision is missing or stale.');
        }

        [$actor] = $this->runs->authorize($run);

        foreach ($tool->requiredPermissions as $permission) {
            try {
                $allowed = $actor->hasRole('admin', 'crm') || $actor->hasPermissionTo($permission, 'crm');
            } catch (\Throwable) {
                $allowed = false;
            }

            if (! $allowed) {
                throw new PolicyViolation('tool_permission_revoked', 'Actor permission was denied during tool re-authorization.');
            }
        }
    }

    private function moneyUnits(mixed $amount): int
    {
        return (int) round(((float) $amount) * 10_000);
    }
}
