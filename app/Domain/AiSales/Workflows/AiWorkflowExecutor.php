<?php

namespace App\Domain\AiSales\Workflows;

use App\Domain\AiSales\Contracts\FakeAiProviderInterface;
use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Domain\AiSales\Runs\AiAgentRunStateMachine;
use App\Domain\AiSales\Runs\AiRunBudgetGuard;
use App\Domain\AiSales\Runs\AiRunPolicyGuard;
use App\Domain\AiSales\Runs\CompleteAiAgentRun;
use App\Domain\AiSales\Services\AiProviderCapabilityAuthorizationService;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolExecutor;
use App\Domain\AiSales\Tools\AiToolRegistry;
use App\Domain\AiSales\Tools\AiToolRequest;
use App\Domain\AiSales\Tools\AiToolSchemaValidator;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\AiPolicyDecisionRecord;
use App\Models\AiToolCall;
use App\Models\AiUsageRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiWorkflowExecutor
{
    public function __construct(
        private readonly AiWorkflowRegistry $workflows,
        private readonly AiToolRegistry $tools,
        private readonly AiToolExecutor $toolExecutor,
        private readonly AiRunPolicyGuard $runPolicy,
        private readonly AiRunBudgetGuard $budgets,
        private readonly AiProviderRegistry $providers,
        private readonly AiProviderCapabilityAuthorizationService $capabilities,
        private readonly AiWorkflowCapabilityGuard $workflowCapabilities,
        private readonly AiToolSchemaValidator $schemas,
        private readonly AiToolDlpGuard $dlp,
        private readonly AiAgentRunStateMachine $state,
        private readonly CompleteAiAgentRun $complete,
    ) {}

    public function execute(AiWorkflowExecutionContext $execution): AiWorkflowResult
    {
        $run = AiAgentRun::query()->findOrFail($execution->runId);
        $step = AiAgentRunStep::query()->findOrFail($execution->runStepId);
        $authoritativeFailureBinding = $step->ai_agent_run_id === $run->id
            && $run->initiator_user_id === $execution->actorUserId
            && $run->lock_version === $execution->expectedRunLockVersion;

        if ($run->status === AiRunStatus::Completed) {
            return $this->replayCompleted($run, $step, $execution);
        }

        try {
            $this->assertExecutionEnabled($run, $step, $execution);
            [$actor] = $this->runPolicy->authorize($run);
            $workflow = $this->workflows->resolveForRun($run);

            if (! $workflow->enabled || ! $workflow->syntheticOnly || $workflow->liveEligible
                || $workflow->humanReviewRequired || $workflow->requiresProviderNativeTools) {
                throw new PolicyViolation('workflow_disabled', 'Only the development/test synthetic server-owned workflow is enabled.');
            }

            try {
                $canExecute = $actor->hasRole('admin', 'crm')
                    || $actor->hasPermissionTo('ai_sales.workflows.execute', 'crm');
            } catch (Throwable) {
                $canExecute = false;
            }

            if (! $canExecute) {
                throw new PolicyViolation('workflow_permission_revoked', 'Actor permission was denied during workflow re-authorization.');
            }

            $policy = AiPolicyDecisionRecord::query()
                ->where('ai_agent_run_id', $run->id)
                ->where('ai_agent_run_step_id', $step->id)
                ->where('decision_hash', $run->policy_decision_hash)
                ->whereIn('decision', ['allow', 'redact'])
                ->latest('id')
                ->first();

            if (! $policy) {
                throw new PolicyViolation('workflow_policy_decision_missing', 'Workflow requires a current persisted policy decision.');
            }

            $startedAt = hrtime(true);
            $toolResults = [];

            foreach ($workflow->steps as $workflowStep) {
                $tool = $this->tools->get($workflowStep->toolCode, $workflowStep->toolVersion);
                $inputHash = AiCanonicalJson::hash($workflowStep->fixedInput);
                $toolResults[] = $this->toolExecutor->execute(
                    new AiToolExecutionContext(
                        $run->id,
                        $step->id,
                        $actor->id,
                        $run->unit_id,
                        $run->unit_business_context_id,
                        $run->lane,
                        $run->role_code,
                        $run->purpose,
                        $run->audience,
                        $run->selected_contour,
                        $workflow->code,
                        $workflow->version,
                        $workflow->workflowHash,
                        $policy->id,
                        $policy->decision_hash,
                        $inputHash,
                        $run->lock_version,
                        min($tool->maxRows, $workflow->maxRows),
                        min($tool->maxBytes, $workflow->maxBytes),
                        min($tool->maxDurationMs, $workflow->maxDurationMs),
                        '0.0000',
                        true,
                    ),
                    new AiToolRequest(
                        $workflowStep->toolCode,
                        $workflowStep->toolVersion,
                        $workflowStep->fixedInput,
                        hash('sha256', $execution->callerIdempotencyKey.':'.$workflowStep->sequence),
                    ),
                );
            }

            if (collect($toolResults)->contains(fn ($result) => $result->replayed)) {
                throw new PolicyViolation('workflow_partial_replay_blocked', 'A partially completed workflow cannot replay provider execution.');
            }

            $run = $run->fresh();
            $step = $step->fresh();
            $this->assertExecutionEnabled($run, $step, $execution);
            $this->budgets->assertBeforeProviderCall($run);

            $route = match ($workflow->requiredContour) {
                \App\Domain\AiSales\Enums\AiProcessingContour::ExternalSanitized => AiProviderRoute::ExternalSanitized,
                \App\Domain\AiSales\Enums\AiProcessingContour::LocalRu => AiProviderRoute::LocalRu,
                default => throw new PolicyViolation('workflow_contour_blocked', 'Workflow contour cannot select a provider.'),
            };
            $provider = $this->providers->forRoute($route);

            if (! $provider instanceof FakeAiProviderInterface
                || $provider->code() !== 'fake'
                || config('ai-sales.transport_mode') !== 'fake_only') {
                throw new PolicyViolation('workflow_provider_blocked', 'Stage 07 workflows can use only the deterministic fake provider.');
            }

            $requirements = new AiRequestRequirements(
                $workflow->requiredProviderCapabilities,
                min(4_000, $workflow->maxTokens),
                min(1_000, $workflow->maxTokens),
                true,
            );
            $profile = $provider->capabilities(AiModelProfile::StandardResearch);
            $this->workflowCapabilities->assertCompatible($workflow, $provider->code(), $profile->modelId);

            if ($profile->contour !== $workflow->requiredContour
                || $profile->route !== $route
                || ! $provider->supports($requirements)
                || ! $profile->supports($requirements)) {
                throw new PolicyViolation('workflow_provider_capability_blocked', 'Fake provider capability does not match the workflow.');
            }

            $this->capabilities->authorize(
                $provider->code(),
                $route,
                $profile->modelId,
                $profile->contour,
                $requirements,
            );

            if (! $provider->healthCheck()->available) {
                throw new PolicyViolation('workflow_provider_unavailable', 'The selected fake provider is unavailable.');
            }

            $toolPayload = ['items' => collect($toolResults)->flatMap(fn ($result) => $result->items)->values()->all()];
            $workflowInputHash = AiCanonicalJson::hash($toolPayload);
            $promptHash = hash('sha256', 'stage07:server-owned:synthetic-good-context-classification:v1');
            $request = new AiProviderRequest(
                $run->public_id,
                $step->sequence,
                $workflow->requiredContour,
                AiModelProfile::StandardResearch,
                [
                    new AiProviderInputItem('instruction', 'stage07_server_owned_instruction', [
                        'template' => 'Classify only the repository-owned fictional Safe DTO. Never request or invoke tools.',
                    ]),
                    new AiProviderInputItem('sanitized_data', 'stage07_synthetic_tool_result', $toolPayload),
                ],
                $workflow->responseSchema,
                [],
                $requirements,
                hash('sha256', $workflow->workflowHash.':'.$execution->callerIdempotencyKey),
                $run->policy_decision_hash,
                $promptHash,
                $workflow->responseSchemaHash,
                $workflowInputHash,
                ['public' => collect($toolResults)->sum('rowCount')],
                false,
                min(30, max(1, (int) config('ai-sales.limits.request_timeout_seconds', 30))),
                true,
            );

            $run = $this->state->transition($run, AiRunStatus::Sent, [
                'actual_provider' => $provider->code(),
                'actual_route' => $route->value,
                'actual_model' => $profile->modelId,
                'started_at' => now(),
            ]);
            $step->update([
                'provider_code' => $provider->code(),
                'provider_route' => $route->value,
                'model_id' => $profile->modelId,
                'status' => AiRunStepStatus::Sent,
                'started_at' => now(),
            ]);

            $response = $provider->createResponse($request);
            $run = $this->state->transition($run, AiRunStatus::Processing);
            $step->update([
                'status' => AiRunStepStatus::Processing,
                'provider_request_id' => $response->requestId,
            ]);

            if ($response->toolCalls !== [] || $response->usage->toolCallCount !== 0) {
                throw new PolicyViolation('workflow_unexpected_native_tool_call', 'Unexpected provider tool calls are a protocol violation.');
            }

            if ($response->providerCode !== 'fake'
                || $response->providerRoute !== $route->value
                || $response->modelId !== $profile->modelId
                || $response->status !== AiProviderResponseStatus::Completed) {
                throw new PolicyViolation('workflow_provider_protocol_violation', 'Provider response did not match the fixed workflow contract.');
            }

            if (count($response->outputItems) !== 1
                || $response->outputItems[0]->type !== 'structured') {
                throw new PolicyViolation('workflow_response_schema_invalid', 'Workflow response must contain one structured item.');
            }

            $this->schemas->assertValid($workflow->responseSchema, $response->outputItems[0]->data, 'workflow_output');
            $this->dlp->assertSafe($response->outputItems[0]->data, new AiToolExecutionContext(
                $run->id,
                $step->id,
                $run->initiator_user_id,
                $run->unit_id,
                $run->unit_business_context_id,
                $run->lane,
                $run->role_code,
                $run->purpose,
                $run->audience,
                $run->selected_contour,
                $workflow->code,
                $workflow->version,
                $workflow->workflowHash,
                $policy->id,
                $policy->decision_hash,
                $workflowInputHash,
                $run->lock_version,
                $workflow->maxRows,
                $workflow->maxBytes,
                $workflow->maxDurationMs,
                $workflow->maxCostRub,
                true,
            ));
            $this->budgets->assertUsageFits($run, $response->usage);
            $durationMs = (int) ceil((hrtime(true) - $startedAt) / 1_000_000);

            if ($durationMs > $workflow->maxDurationMs) {
                throw new PolicyViolation('workflow_time_budget_exceeded', 'Workflow elapsed time exceeded its code-owned cap.');
            }

            $rowCount = collect($toolResults)->sum('rowCount');
            $byteCount = collect($toolResults)->sum('byteCount');

            DB::transaction(function () use (
                $run,
                $step,
                $response,
                $workflow,
                $toolResults,
                $rowCount,
                $byteCount,
            ): void {
                $step->update([
                    'status' => AiRunStepStatus::Completed,
                    'normalized_output_metadata' => [
                        'response_status' => 'completed',
                        'output_item_count' => 1,
                        'output_types' => ['structured'],
                        'schema_valid' => true,
                        'workflow_code' => $workflow->code,
                        'workflow_version' => $workflow->version,
                        'workflow_hash' => $workflow->workflowHash,
                        'response_schema_hash' => $workflow->responseSchemaHash,
                        'server_owned_tool_call_count' => count($toolResults),
                        'provider_native_tool_call_count' => 0,
                        'row_count' => $rowCount,
                        'byte_count' => $byteCount,
                        'fallback_allowed' => false,
                    ],
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
                    'operation' => 'ai_sales_server_owned_synthetic_workflow',
                    'model' => $response->modelId,
                    'capability' => 'server_owned_workflow',
                    'endpoint' => 'fake_only',
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'reasoning_tokens' => $response->usage->reasoningTokens,
                    'cached_tokens' => $response->usage->cachedTokens,
                    'search_count' => 0,
                    'tool_call_count' => count($toolResults),
                    'total_tokens' => $response->usage->totalTokens(),
                    'status' => 'success',
                    'estimated_cost' => $response->usage->providerAmount,
                    'cost_currency' => $response->usage->providerCurrency,
                    'cost_is_estimate' => true,
                    'normalized_rub_amount' => $response->usage->normalizedRubAmount,
                    'external_request_id' => $response->requestId,
                    'prompt_version' => $workflow->version,
                    'schema_version' => $workflow->version,
                    'metadata' => [
                        'source' => 'stage07_server_owned_workflow',
                        'transport' => 'fake_only',
                        'workflow_hash' => $workflow->workflowHash,
                    ],
                ]);
            }, 3);

            $completed = $this->complete->handle($run, $response->usage);

            return new AiWorkflowResult(
                $completed->status->value,
                $workflow->code,
                $workflow->version,
                $workflow->workflowHash,
                count($toolResults),
                $rowCount,
                $byteCount,
                $durationMs,
            );
        } catch (PolicyViolation $violation) {
            $this->blockRun($run, $step, $authoritativeFailureBinding, $violation);

            throw $violation;
        } catch (Throwable) {
            $violation = new PolicyViolation('workflow_execution_failed', 'Workflow execution failed safely.');
            $this->blockRun($run, $step, $authoritativeFailureBinding, $violation);

            throw $violation;
        }
    }

    private function assertExecutionEnabled(
        AiAgentRun $run,
        AiAgentRunStep $step,
        AiWorkflowExecutionContext $execution,
    ): void {
        if (! app()->environment(['local', 'testing'])
            || ! (bool) config('ai-sales.tools.enabled', false)
            || ! (bool) config('ai-sales.workflows.enabled', false)
            || (bool) config('ai-sales.live_business_workflows_enabled', false)
            || (bool) config('ai-sales.provider_native_tools_enabled', false)
            || (bool) config('ai-sales.external_calls_enabled', false)
            || (bool) config('ai-sales.provider_failover_enabled', false)) {
            throw new PolicyViolation('workflow_feature_guard_blocked', 'Stage 07 synthetic workflow flags are not in a safe state.');
        }

        if ($run->id !== $execution->runId
            || $step->id !== $execution->runStepId
            || $step->ai_agent_run_id !== $run->id
            || $run->initiator_user_id !== $execution->actorUserId
            || $run->lock_version !== $execution->expectedRunLockVersion
            || $run->status !== AiRunStatus::Ready
            || $step->status !== AiRunStepStatus::Ready
            || $run->cancelled_at !== null) {
            throw new PolicyViolation('workflow_execution_binding_mismatch', 'Workflow run, step, actor, lock or state binding is stale.');
        }
    }

    private function replayCompleted(
        AiAgentRun $run,
        AiAgentRunStep $step,
        AiWorkflowExecutionContext $execution,
    ): AiWorkflowResult {
        if ($run->initiator_user_id !== $execution->actorUserId
            || $step->ai_agent_run_id !== $run->id
            || $step->id !== $execution->runStepId) {
            throw new PolicyViolation('workflow_idempotency_conflict', 'Completed workflow belongs to a different binding.');
        }

        $call = AiToolCall::query()
            ->where('ai_agent_run_id', $run->id)
            ->where('ai_agent_run_step_id', $step->id)
            ->where('status', 'completed')
            ->whereNotNull('workflow_hash')
            ->first();

        if (! $call) {
            throw new PolicyViolation('workflow_replay_unavailable', 'Completed run has no safe workflow execution record.');
        }

        $workflow = $this->workflows->get((string) $call->workflow_code, (string) $call->workflow_version);
        $workflowStep = collect($workflow->steps)->first(
            static fn (AiWorkflowStepDefinition $candidate): bool => $candidate->toolCode === $call->tool_code
                && $candidate->toolVersion === $call->tool_version,
        );
        $toolIdempotencyKey = $workflowStep instanceof AiWorkflowStepDefinition
            ? hash('sha256', $execution->callerIdempotencyKey.':'.$workflowStep->sequence)
            : '';
        $expectedIdempotencyKey = hash('sha256', implode(':', [
            $run->id,
            $step->id,
            $execution->actorUserId,
            $workflow->workflowHash,
            $call->tool_code,
            $call->tool_version,
            $toolIdempotencyKey,
        ]));

        if (! $workflowStep instanceof AiWorkflowStepDefinition
            || $call->actor_user_id !== $execution->actorUserId
            || ! hash_equals($workflow->workflowHash, (string) $call->workflow_hash)
            || ! hash_equals($expectedIdempotencyKey, (string) $call->idempotency_key)) {
            throw new PolicyViolation('workflow_idempotency_conflict', 'Completed workflow belongs to another idempotency binding.');
        }

        return new AiWorkflowResult(
            'completed',
            (string) $call->workflow_code,
            (string) $call->workflow_version,
            (string) $call->workflow_hash,
            1,
            (int) $call->row_count,
            (int) $call->byte_count,
            (int) $call->duration_ms,
            null,
            true,
        );
    }

    private function blockRun(
        AiAgentRun $run,
        AiAgentRunStep $step,
        bool $authoritativeFailureBinding,
        PolicyViolation $violation,
    ): void {
        $run = $run->fresh();
        $step = $step->fresh();

        if (! $run || ! $step || $run->status->terminal()
            || $step->ai_agent_run_id !== $run->id
            || ! $authoritativeFailureBinding) {
            return;
        }

        $status = match (true) {
            str_contains($violation->errorCode, 'budget') => AiRunStatus::BudgetExceeded,
            str_contains($violation->errorCode, 'dlp'), str_contains($violation->errorCode, 'untrusted') => AiRunStatus::BlockedByDlp,
            str_contains($violation->errorCode, 'contour') => AiRunStatus::BlockedByContour,
            str_contains($violation->errorCode, 'protocol'), str_contains($violation->errorCode, 'native_tool') => AiRunStatus::BlockedByPolicy,
            str_contains($violation->errorCode, 'provider'), str_contains($violation->errorCode, 'capability') => AiRunStatus::ProviderUnavailable,
            default => AiRunStatus::BlockedByPolicy,
        };

        $step->update([
            'status' => AiRunStepStatus::Blocked,
            'safe_error_code' => mb_substr($violation->errorCode, 0, 96),
            'safe_error_summary' => 'Server-owned workflow was blocked safely.',
            'completed_at' => now(),
        ]);
        $this->state->transition($run, $status, [
            'safe_error_code' => mb_substr($violation->errorCode, 0, 96),
            'safe_error_summary' => 'Server-owned workflow was blocked safely; no retry or fallback was attempted.',
            'completed_at' => now(),
        ]);
    }
}
