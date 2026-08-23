<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Domain\AiSales\Services\AiStage04FeatureGuard;
use App\Models\AiAgentDefinition;
use App\Models\AiAgentRun;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;

class AiRunPolicyGuard
{
    public function __construct(
        private readonly AiControlPlaneAuthorizationService $authorization,
        private readonly AiStage04FeatureGuard $features,
    ) {}

    /** @return array{0: User, 1: Unit, 2: UnitBusinessContext, 3: AiAgentDefinition} */
    public function authorize(AiAgentRun $run): array
    {
        if ($run->status->terminal() || $run->cancelled_at !== null) {
            throw new PolicyViolation('run_not_executable', 'Cancelled or terminal AI runs cannot execute.');
        }

        if ($run->expires_at === null
            || ! $run->expires_at->isFuture()
            || ($run->queued_at !== null
                && $run->queued_at->diffInSeconds(now()) > (int) config('ai-sales.limits.max_wall_clock_seconds', 0))) {
            throw new PolicyViolation('run_expired', 'AI run exceeded its code-owned wall-clock limit.');
        }

        $actor = User::query()->find($run->initiator_user_id);
        $unit = Unit::query()
            ->without(['fields', 'labels', 'telephones', 'uris'])
            ->select(['id', 'name'])
            ->find($run->unit_id);
        $context = UnitBusinessContext::query()->find($run->unit_business_context_id);
        $definition = AiAgentDefinition::query()->find($run->ai_agent_definition_id);

        if (! $actor || ! $unit || ! $context || ! $definition || ! $definition->enabled) {
            throw new PolicyViolation('run_subject_unavailable', 'Run actor, Unit, context or enabled definition is unavailable.');
        }

        if (! $this->authorization->canRun($actor, $unit, $context)) {
            throw new PolicyViolation('run_reauthorization_failed', 'Run actor is no longer authorized for this Unit context.');
        }

        if ($context->archived_at !== null || $context->status !== UnitContextStatus::Active) {
            throw new PolicyViolation('unit_context_inactive', 'AI run requires a current active Unit business context.');
        }

        if ($context->lane !== $run->lane || $context->role_code !== $run->role_code) {
            throw new PolicyViolation('unit_context_snapshot_stale', 'Unit context lane/role no longer matches the immutable run snapshot.');
        }

        if ($definition->code !== $run->definition_code
            || $definition->version !== $run->definition_version
            || $definition->prompt_hash !== $run->prompt_hash
            || $definition->schema_hash !== $run->schema_hash) {
            throw new PolicyViolation('agent_definition_snapshot_stale', 'Agent definition no longer matches the immutable run snapshot.');
        }

        if ($run->task_profile === AiTaskProfile::OutreachDrafting
            && ($context->stage === UnitContextStage::DoNotContact || ! (bool) config('ai-sales.outreach_drafting_enabled', false))) {
            throw new PolicyViolation('outreach_drafting_blocked', 'Outreach drafting is disabled or the Unit context is do-not-contact.');
        }

        $this->features->assertEnabled($run->requested_contour);

        return [$actor, $unit, $context, $definition];
    }
}
