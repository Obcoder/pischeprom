<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Policies\AiProcessingContourPolicy;
use App\Domain\AiSales\Queries\UnitSharedPublicProfileQuery;
use App\Domain\AiSales\Services\AiContextSanitizer;
use App\Domain\AiSales\Services\AiPromptSchemaRegistry;
use App\Domain\AiSales\Services\AiResidencyAuthorizationService;
use App\Domain\AiSales\Services\AiTaskProfileRegistry;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\AiDataAccessLog;
use App\Models\AiPolicyDecisionRecord;
use App\Models\AiRedactionEvent;
use Illuminate\Support\Facades\DB;

class PrepareAiAgentRun
{
    public function __construct(
        private readonly AiAgentRunStateMachine $state,
        private readonly AiRunPolicyGuard $guard,
        private readonly UnitSharedPublicProfileQuery $profiles,
        private readonly AiProcessingContourPolicy $contours,
        private readonly AiTaskProfileRegistry $taskProfiles,
        private readonly AiResidencyAuthorizationService $residency,
        private readonly AiContextSanitizer $sanitizer,
        private readonly DeterministicAiPayloadScanner $dlp,
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiPromptSchemaRegistry $prompts,
    ) {}

    public function handle(AiAgentRun $run): AiAgentRun
    {
        if ($run->status !== AiRunStatus::Queued) {
            return $run->fresh();
        }

        $run = $this->state->transition($run, AiRunStatus::Preparing);

        try {
            [$actor, $unit, $context, $definition] = $this->guard->authorize($run);
            $prompt = $this->prompts->get($definition);
        } catch (PolicyViolation $violation) {
            return $this->block($run, $violation);
        }

        $run = $this->state->transition($run, AiRunStatus::PolicyCheck);
        $modelProfile = $run->model_profile_preference;

        try {
            $dto = $this->profiles->get($unit->id);
            $modelId = $this->taskProfiles->modelId($run->requested_contour, $modelProfile);
            $residency = $run->requested_contour === AiProcessingContour::LocalRu
                ? $this->residency->find('fake', 'local_ru', $modelId)
                : null;
            $disclosureContext = new AiDisclosureContext(
                $unit->id,
                $context->id,
                $context->lane,
                $context->role_code,
                $run->audience,
                $run->purpose,
                $run->requested_contour === AiProcessingContour::ExternalSanitized,
            );
            $decision = $this->contours->decide(
                $dto,
                $disclosureContext,
                $run->task_profile,
                $run->requested_contour,
                $residency,
            );

            if (! $decision->permitsProviderSelection()) {
                $this->recordPolicyDecision($run, $decision);

                return $this->block(
                    $run,
                    new PolicyViolation($decision->reasonCode, 'AI processing contour decision did not permit execution.'),
                    $decision->decisionHash,
                );
            }

            $sanitized = $this->sanitizer->sanitize($dto, new AiDisclosureContext(
                $unit->id,
                $context->id,
                $context->lane,
                $context->role_code,
                $run->audience,
                $run->purpose,
                $decision->selectedContour === AiProcessingContour::ExternalSanitized,
            ));
            $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $inputHash = hash('sha256', $encoded);
            $scan = $this->dlp->scan($sanitized, $decision->selectedContour);

            if ($scan->blocked()) {
                return $this->block($run, new PolicyViolation('dlp_recheck_blocked', 'DLP blocked the prepared Safe DTO.'), $decision->decisionHash);
            }

            $step = DB::transaction(function () use (
                $run,
                $actor,
                $unit,
                $dto,
                $decision,
                $inputHash,
                $encoded,
                $modelId,
                $scan,
            ): AiAgentRunStep {
                $step = AiAgentRunStep::query()->create([
                    'ai_agent_run_id' => $run->id,
                    'sequence' => 1,
                    'step_type' => 'synthetic_unit_profile',
                    'contour' => $decision->selectedContour,
                    'model_id' => $modelId,
                    'sanitized_input_hash' => $inputHash,
                    'safe_request_summary' => sprintf('%s: %d allowlisted fields, %d bytes.', class_basename($dto), count($dto->fields()), strlen($encoded)),
                    'status' => AiRunStepStatus::Ready,
                ]);

                $this->recordPolicyDecision($run, $decision, $step);

                AiDataAccessLog::query()->create([
                    'ai_agent_run_id' => $run->id,
                    'dto_type' => $dto::class,
                    'source_type' => 'unit_shared_public_profile',
                    'source_id' => $unit->id,
                    'contour' => $decision->selectedContour,
                    'classification_summary' => $decision->classificationSummary,
                    'row_count' => count($dto->fields()),
                    'byte_count' => strlen($encoded),
                    'decision' => $decision->decision,
                    'actor_user_id' => $actor->id,
                ]);

                foreach ($scan->findings as $finding) {
                    AiRedactionEvent::query()->create([
                        'ai_agent_run_id' => $run->id,
                        'ai_agent_run_step_id' => $step->id,
                        'detector' => $finding->detector,
                        'rule_code' => $finding->ruleCode,
                        'finding_type' => $finding->type,
                        'action' => $finding->action,
                        'path_hash' => $finding->pathHash,
                        'occurrences' => $finding->occurrences,
                    ]);
                }

                foreach ($dto->fields() as $field => $_value) {
                    $rule = $this->registry->find($dto::class, (string) $field);

                    if ($rule?->redactionRule === 'mask') {
                        AiRedactionEvent::query()->create([
                            'ai_agent_run_id' => $run->id,
                            'ai_agent_run_step_id' => $step->id,
                            'detector' => 'classification_registry',
                            'rule_code' => 'code_owned_mask',
                            'finding_type' => $rule->classification->value,
                            'action' => 'redact',
                            'path_hash' => hash('sha256', $dto::class.'.'.$field),
                            'occurrences' => 1,
                        ]);
                    }
                }

                return $step;
            }, 3);

            return $this->state->transition($run, AiRunStatus::Ready, [
                'selected_contour' => $decision->selectedContour,
                'policy_decision_hash' => $decision->decisionHash,
                'safe_input_summary' => $step->safe_request_summary,
                'safe_input_hash' => $inputHash,
                'prepared_at' => now(),
            ]);
        } catch (PolicyViolation $violation) {
            return $this->block($run, $violation);
        } catch (\Throwable) {
            return $this->block($run, new PolicyViolation('run_preparation_failed', 'AI run preparation failed safely.'));
        }
    }

    private function block(AiAgentRun $run, PolicyViolation $violation, ?string $decisionHash = null): AiAgentRun
    {
        $status = match (true) {
            str_contains($violation->errorCode, 'residency') => AiRunStatus::ResidencyUnverified,
            str_contains($violation->errorCode, 'dlp') => AiRunStatus::BlockedByDlp,
            str_contains($violation->errorCode, 'contour') => AiRunStatus::BlockedByContour,
            default => AiRunStatus::BlockedByPolicy,
        };

        return $this->state->transition($run, $status, [
            'policy_decision_hash' => $decisionHash ?? $run->policy_decision_hash,
            'safe_error_code' => mb_substr($violation->errorCode, 0, 96),
            'safe_error_summary' => mb_substr($violation->getMessage(), 0, 512),
            'completed_at' => now(),
        ]);
    }

    private function recordPolicyDecision(
        AiAgentRun $run,
        \App\Domain\AiSales\DTO\Routing\AiProcessingRouteDecision $decision,
        ?AiAgentRunStep $step = null,
    ): AiPolicyDecisionRecord {
        return AiPolicyDecisionRecord::query()->create([
            'ai_agent_run_id' => $run->id,
            'ai_agent_run_step_id' => $step?->id,
            'disclosure_policy_version' => $decision->disclosurePolicyVersion,
            'contour_policy_version' => $decision->contourPolicyVersion,
            'classification_snapshot' => $decision->classificationSummary,
            'visibility_snapshot' => $decision->visibilitySummary,
            'decision' => $decision->decision,
            'contour' => $decision->selectedContour,
            'reason_code' => $decision->reasonCode,
            'redaction_count' => $decision->redactionCount,
            'requires_human_review' => $decision->requiresHumanReview,
            'decision_hash' => $decision->decisionHash,
        ]);
    }
}
