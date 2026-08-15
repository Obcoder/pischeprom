<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Routing\AiProcessingRouteDecision;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Policies\AiProcessingContourPolicy;
use App\Domain\AiSales\Providers\AiProviderRouter;
use App\Domain\AiSales\Queries\UnitSharedPublicProfileQuery;
use App\Domain\AiSales\Services\AiContextSanitizer;
use App\Domain\AiSales\Services\AiPromptSchemaRegistry;
use App\Domain\AiSales\Services\AiResidencyAuthorizationService;
use App\Domain\AiSales\Services\AiTaskProfileRegistry;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\AiPolicyDecisionRecord;
use App\Models\AiToolCall;
use App\Models\AiUsageRecord;
use Illuminate\Support\Facades\DB;

class ExecuteAiAgentRunStep
{
    public function __construct(
        private readonly AiAgentRunStateMachine $state,
        private readonly AiRunPolicyGuard $guard,
        private readonly AiRunBudgetGuard $budgets,
        private readonly UnitSharedPublicProfileQuery $profiles,
        private readonly AiProcessingContourPolicy $contours,
        private readonly AiTaskProfileRegistry $taskProfiles,
        private readonly AiResidencyAuthorizationService $residency,
        private readonly AiContextSanitizer $sanitizer,
        private readonly DeterministicAiPayloadScanner $dlp,
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiPromptSchemaRegistry $prompts,
        private readonly AiProviderRouter $router,
        private readonly CompleteAiAgentRun $complete,
    ) {}

    public function handle(AiAgentRun $run): AiAgentRun
    {
        if ($run->status !== AiRunStatus::Ready) {
            return $run->fresh();
        }

        $step = $run->steps()->where('sequence', 1)->firstOrFail();

        try {
            [$actor, $unit, $context, $definition] = $this->guard->authorize($run);
            $this->budgets->assertBeforeProviderCall($run);
            $prompt = $this->prompts->get($definition);
            $dto = $this->profiles->get($unit->id);
            $modelProfile = $run->model_profile_preference;
            $modelId = $this->taskProfiles->modelId($run->selected_contour, $modelProfile);
            $residency = $run->selected_contour === AiProcessingContour::LocalRu
                ? $this->residency->find('fake', 'local_ru', $modelId)
                : null;
            $disclosure = new AiDisclosureContext(
                $unit->id,
                $context->id,
                $context->lane,
                $context->role_code,
                $run->audience,
                $run->purpose,
                $run->selected_contour === AiProcessingContour::ExternalSanitized,
            );
            $decision = $this->contours->decide(
                $dto,
                $disclosure,
                $run->task_profile,
                $run->selected_contour,
                $residency,
            );
            $this->assertFreshPolicyDecision($run, $decision);
            $sanitized = $this->sanitizer->sanitize($dto, $disclosure);
            $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $inputHash = hash('sha256', $encoded);

            if (! hash_equals((string) $run->safe_input_hash, $inputHash)
                || ! hash_equals($step->sanitized_input_hash, $inputHash)) {
                throw new PolicyViolation('safe_input_snapshot_stale', 'Safe DTO changed after the policy decision; a new run is required.');
            }

            $scan = $this->dlp->scan($sanitized, $run->selected_contour);

            if ($scan->blocked()) {
                throw new PolicyViolation('dlp_execution_recheck_blocked', 'DLP blocked the reconstructed Safe DTO.');
            }

            $requirements = $this->taskProfiles->requirements($run->task_profile);
            $selection = $this->router->select($decision, $modelProfile, $requirements);
            $containsLocalOnlyData = $this->containsLocalOnlyData($dto);
            $request = new AiProviderRequest(
                $run->public_id,
                $step->sequence,
                $run->selected_contour,
                $modelProfile,
                [
                    new AiProviderInputItem('instruction', 'code_owned_prompt', ['template' => $prompt['template']]),
                    new AiProviderInputItem('sanitized_data', 'unit_shared_public_profile', $sanitized),
                ],
                $prompt['schema'],
                [],
                $requirements,
                hash('sha256', $run->idempotency_key.':'.$step->sequence),
                $decision->decisionHash,
                $prompt['prompt_hash'],
                $prompt['schema_hash'],
                $inputHash,
                $decision->classificationSummary,
                $containsLocalOnlyData,
                min(60, max(1, (int) config('ai-sales.limits.request_timeout_seconds', 30))),
            );

            $run = $this->state->transition($run, AiRunStatus::Sent, [
                'actual_provider' => $selection->providerCode,
                'actual_route' => $selection->route->value,
                'actual_model' => $selection->modelId,
                'started_at' => now(),
            ]);
            $step->update([
                'provider_code' => $selection->providerCode,
                'provider_route' => $selection->route->value,
                'model_id' => $selection->modelId,
                'status' => AiRunStepStatus::Sent,
                'started_at' => now(),
            ]);

            $response = $this->router->createResponse($selection, $decision, $request);
            $run = $this->state->transition($run, AiRunStatus::Processing);
            $step->update(['status' => AiRunStepStatus::Processing, 'provider_request_id' => $response->requestId]);

            if ($response->status === AiProviderResponseStatus::Failed) {
                return $this->providerFailure($run, $step, $response->error);
            }

            if ($response->usage->toolCallCount !== count($response->toolCalls)) {
                throw new PolicyViolation('provider_usage_mismatch', 'Normalized tool-call usage does not match the provider response.');
            }

            $this->budgets->assertUsageFits($run, $response->usage);
            $metadata = [
                'response_status' => $response->status->value,
                'output_item_count' => count($response->outputItems),
                'output_types' => collect($response->outputItems)->map(fn ($item) => $item->type)->unique()->values()->take(10)->all(),
                'tool_call_count' => count($response->toolCalls),
                'citation_count' => count($response->citations),
                'schema_version' => $definition->schema_version,
                'schema_valid' => true,
                'provider_selection_hash' => $selection->decisionHash,
                'provider_selection_reason' => $selection->reasonCode,
                'verified_capabilities' => $selection->verifiedCapabilities,
            ];

            DB::transaction(function () use ($run, $step, $response, $metadata, $decision, $context): void {
                $step->update([
                    'status' => $response->status === AiProviderResponseStatus::RequiresAction
                        ? AiRunStepStatus::RequiresAction
                        : AiRunStepStatus::Completed,
                    'normalized_output_metadata' => $metadata,
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'reasoning_tokens' => $response->usage->reasoningTokens,
                    'normalized_cost_rub' => $response->usage->normalizedRubAmount,
                    'completed_at' => now(),
                ]);

                AiUsageRecord::query()->create([
                    'ai_agent_run_id' => $run->id,
                    'ai_agent_run_step_id' => $step->id,
                    'contour' => $run->selected_contour,
                    'provider' => $response->providerCode,
                    'provider_route' => $response->providerRoute,
                    'operation' => 'ai_sales_synthetic_response',
                    'model' => $response->modelId,
                    'capability' => 'synthetic_response',
                    'endpoint' => 'fake_only',
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'reasoning_tokens' => $response->usage->reasoningTokens,
                    'cached_tokens' => $response->usage->cachedTokens,
                    'search_count' => $response->usage->searchCount,
                    'tool_call_count' => $response->usage->toolCallCount,
                    'total_tokens' => $response->usage->totalTokens(),
                    'status' => 'success',
                    'estimated_cost' => $response->usage->providerAmount,
                    'cost_currency' => $response->usage->providerCurrency,
                    'cost_is_estimate' => true,
                    'normalized_rub_amount' => $response->usage->normalizedRubAmount,
                    'external_request_id' => $response->requestId,
                    'prompt_version' => $run->definition_version,
                    'schema_version' => $run->definition->schema_version,
                    'metadata' => [
                        'source' => 'ai_sales_control_plane',
                        'transport' => 'fake_only',
                    ],
                ]);

                foreach (array_slice($response->toolCalls, 0, 8) as $toolCall) {
                    AiToolCall::query()->create([
                        'ai_agent_run_id' => $run->id,
                        'ai_agent_run_step_id' => $step->id,
                        'call_id' => mb_substr($toolCall->callId, 0, 128),
                        'tool_code' => mb_substr($toolCall->toolCode, 0, 96),
                        'tool_version' => mb_substr($toolCall->toolVersion, 0, 32),
                        'contour' => $run->selected_contour,
                        'unit_id' => $run->unit_id,
                        'unit_business_context_id' => $run->unit_business_context_id,
                        'context_snapshot' => [
                            'unit_id' => $run->unit_id,
                            'context_id' => $context->id,
                            'lane' => $context->lane->value,
                            'role_code' => $context->role_code->value,
                        ],
                        'arguments_hash' => $toolCall->argumentsHash,
                        'redacted_arguments_summary' => 'Synthetic tool arguments retained as hash only.',
                        'authorization_decision' => 'pending_local_authorization',
                        'policy_decision_hash' => $decision->decisionHash,
                        'idempotency_key' => hash('sha256', $run->id.':'.$toolCall->callId),
                        'side_effect_class' => 'read_only',
                        'status' => 'requires_action',
                    ]);
                }
            }, 3);

            if ($response->status === AiProviderResponseStatus::RequiresAction) {
                return $this->state->transition($run, AiRunStatus::RequiresAction, [
                    'accumulated_tokens' => $run->accumulated_tokens + $response->usage->totalTokens(),
                    'accumulated_cost_rub' => number_format((float) $run->accumulated_cost_rub + (float) $response->usage->normalizedRubAmount, 4, '.', ''),
                    'current_step' => $run->current_step + 1,
                ]);
            }

            return $this->complete->handle($run, $response->usage);
        } catch (PolicyViolation $violation) {
            return $this->block($run, $step, $violation);
        } catch (\Throwable) {
            return $this->block($run, $step, new PolicyViolation('run_execution_failed', 'AI run execution failed safely.'));
        }
    }

    private function assertFreshPolicyDecision(AiAgentRun $run, AiProcessingRouteDecision $decision): void
    {
        if (! $decision->permitsProviderSelection()
            || ! hash_equals((string) $run->policy_decision_hash, $decision->decisionHash)
            || $decision->selectedContour !== $run->selected_contour) {
            throw new PolicyViolation('policy_decision_stale', 'Policy/contour decision changed before queued execution.');
        }

        AiPolicyDecisionRecord::query()->create([
            'ai_agent_run_id' => $run->id,
            'ai_agent_run_step_id' => $run->steps()->where('sequence', 1)->value('id'),
            'disclosure_policy_version' => $decision->disclosurePolicyVersion,
            'contour_policy_version' => $decision->contourPolicyVersion,
            'classification_snapshot' => $decision->classificationSummary,
            'visibility_snapshot' => $decision->visibilitySummary,
            'decision' => $decision->decision,
            'contour' => $decision->selectedContour,
            'reason_code' => $decision->reasonCode,
            'redaction_count' => $decision->redactionCount,
            'requires_human_review' => false,
            'decision_hash' => $decision->decisionHash,
        ]);
    }

    private function containsLocalOnlyData(object $dto): bool
    {
        foreach ($dto->fields() as $field => $_value) {
            if (! $this->registry->find($dto::class, (string) $field)?->externalExportable) {
                return true;
            }
        }

        return false;
    }

    private function providerFailure(AiAgentRun $run, AiAgentRunStep $step, mixed $error): AiAgentRun
    {
        $status = match ($error?->category) {
            AiProviderErrorCategory::DlpBlocked => AiRunStatus::BlockedByDlp,
            AiProviderErrorCategory::ContourBlocked => AiRunStatus::BlockedByContour,
            AiProviderErrorCategory::ProviderUnavailable,
            AiProviderErrorCategory::CapabilityMissing => AiRunStatus::ProviderUnavailable,
            AiProviderErrorCategory::PolicyBlocked => AiRunStatus::BlockedByPolicy,
            default => AiRunStatus::Failed,
        };
        $step->update([
            'status' => in_array($status, [
                AiRunStatus::BlockedByDlp,
                AiRunStatus::BlockedByContour,
                AiRunStatus::BlockedByPolicy,
            ], true) ? AiRunStepStatus::Blocked : AiRunStepStatus::Failed,
            'safe_error_code' => mb_substr((string) ($error?->safeCode ?? 'provider_failed'), 0, 96),
            'safe_error_summary' => 'Fake provider returned a normalized failure.',
            'completed_at' => now(),
        ]);

        return $this->state->transition($run, $status, [
            'safe_error_code' => mb_substr((string) ($error?->safeCode ?? 'provider_failed'), 0, 96),
            'safe_error_summary' => 'Fake provider returned a normalized failure; no fallback was attempted.',
            'completed_at' => now(),
        ]);
    }

    private function block(AiAgentRun $run, AiAgentRunStep $step, PolicyViolation $violation): AiAgentRun
    {
        $status = match (true) {
            str_contains($violation->errorCode, 'budget') => AiRunStatus::BudgetExceeded,
            str_contains($violation->errorCode, 'residency') => AiRunStatus::ResidencyUnverified,
            str_contains($violation->errorCode, 'dlp') => AiRunStatus::BlockedByDlp,
            str_contains($violation->errorCode, 'contour') => AiRunStatus::BlockedByContour,
            str_contains($violation->errorCode, 'provider') || str_contains($violation->errorCode, 'capability') => AiRunStatus::ProviderUnavailable,
            default => AiRunStatus::BlockedByPolicy,
        };
        $step->update([
            'status' => AiRunStepStatus::Blocked,
            'safe_error_code' => mb_substr($violation->errorCode, 0, 96),
            'safe_error_summary' => mb_substr($violation->getMessage(), 0, 512),
            'completed_at' => now(),
        ]);

        return $this->state->transition($run, $status, [
            'safe_error_code' => mb_substr($violation->errorCode, 0, 96),
            'safe_error_summary' => mb_substr($violation->getMessage(), 0, 512),
            'completed_at' => now(),
        ]);
    }
}
