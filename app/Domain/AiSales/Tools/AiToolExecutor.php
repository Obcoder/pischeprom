<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\DTO\SafeAiDto;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Services\AiContextSanitizer;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Domain\AiSales\Workflows\AiWorkflowRegistry;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\AiDataAccessLog;
use App\Models\AiToolCall;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiToolExecutor
{
    public function __construct(
        private readonly AiToolRegistry $tools,
        private readonly AiWorkflowRegistry $workflows,
        private readonly AiToolSchemaValidator $schemas,
        private readonly AiToolPolicyGuard $policy,
        private readonly AiToolDlpGuard $dlp,
        private readonly AiDataClassificationRegistry $classifications,
        private readonly AiContextSanitizer $sanitizer,
    ) {}

    public function execute(AiToolExecutionContext $execution, AiToolRequest $request): AiToolResult
    {
        $tool = $this->tools->get($request->toolCode, $request->toolVersion);
        $workflow = $this->workflows->get($execution->workflowCode, $execution->workflowVersion);

        if (! hash_equals($workflow->workflowHash, $execution->workflowHash)
            || ! $workflow->allowsTool($tool->code, $tool->version)
            || $workflow->syntheticOnly !== $execution->syntheticOnly) {
            throw new PolicyViolation('tool_workflow_binding_mismatch', 'Tool is not bound to the current code-owned workflow.');
        }

        $this->schemas->assertValid($tool->inputSchema, $request->input, 'tool_input');
        $argumentsHash = AiCanonicalJson::hash($request->input);

        if (! hash_equals($argumentsHash, $execution->safeInputHash)) {
            throw new PolicyViolation('tool_input_hash_mismatch', 'Tool input no longer matches its immutable hash.');
        }

        $idempotencyKey = hash('sha256', implode(':', [
            $execution->runId,
            $execution->runStepId,
            $execution->actorUserId,
            $execution->workflowHash,
            $tool->code,
            $tool->version,
            $request->callerIdempotencyKey,
        ]));
        $existing = AiToolCall::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $this->replay($existing, $execution, $tool, $argumentsHash);
        }

        $run = AiAgentRun::query()->findOrFail($execution->runId);
        $step = AiAgentRunStep::query()->findOrFail($execution->runStepId);
        $this->policy->authorize($tool, $execution, $run, $step);

        if (AiToolCall::query()
            ->where('ai_agent_run_id', $run->id)
            ->where('tool_code', $tool->code)
            ->whereIn('status', ['running', 'completed'])
            ->count() >= $tool->maxCallsPerRun) {
            throw new PolicyViolation('tool_rate_limit_exceeded', 'Tool call count exceeds its per-run limit.');
        }

        $call = AiToolCall::query()->create([
            'ai_agent_run_id' => $run->id,
            'ai_agent_run_step_id' => $step->id,
            'call_id' => 'server-'.substr($idempotencyKey, 0, 32),
            'tool_code' => $tool->code,
            'tool_version' => $tool->version,
            'workflow_code' => $workflow->code,
            'workflow_version' => $workflow->version,
            'workflow_hash' => $workflow->workflowHash,
            'tool_schema_hash' => $tool->schemaHash,
            'tool_policy_version' => $tool->policyVersion,
            'contour' => $execution->contour,
            'unit_id' => $execution->unitId,
            'unit_business_context_id' => $execution->unitBusinessContextId,
            'actor_user_id' => $execution->actorUserId,
            'ai_policy_decision_id' => $execution->policyDecisionId,
            'context_snapshot' => [
                'unit_id' => $execution->unitId,
                'context_id' => $execution->unitBusinessContextId,
                'lane' => $execution->lane->value,
                'role_code' => $execution->role->value,
                'purpose' => $execution->purpose->value,
                'audience' => $execution->audience->value,
                'contour' => $execution->contour->value,
                'synthetic_only' => $execution->syntheticOnly,
            ],
            'arguments_hash' => $argumentsHash,
            'safe_input_hash' => $argumentsHash,
            'redacted_arguments_summary' => count($request->input).' code-owned input fields; values retained as SHA-256 only.',
            'authorization_decision' => 'allow',
            'policy_decision_hash' => $execution->policyDecisionHash,
            'idempotency_key' => $idempotencyKey,
            'side_effect_class' => $tool->sideEffectClass,
            'budget_reservation' => $execution->budgetSnapshot(),
            'status' => 'running',
            'started_at' => now(),
        ]);
        $startedAt = hrtime(true);

        try {
            $preScan = $this->dlp->assertSafe($request->input, $execution);
            [$handlerResult, $queryCount] = $this->runHandler($tool, $execution, $request->input);
            $durationMs = (int) ceil((hrtime(true) - $startedAt) / 1_000_000);

            if ($queryCount > $tool->maxQueries) {
                throw new PolicyViolation('tool_query_budget_exceeded', 'Tool query count exceeded its code-owned cap.');
            }

            if ($durationMs > $tool->maxDurationMs || $durationMs > $execution->reservedDurationMs) {
                throw new PolicyViolation('tool_time_budget_exceeded', 'Tool elapsed time exceeded its code-owned cap.');
            }

            if (count($handlerResult->items) > $tool->maxRows || count($handlerResult->items) > $execution->reservedRows) {
                throw new PolicyViolation('tool_row_budget_exceeded', 'Tool row count exceeded its code-owned cap.');
            }

            [$items, $classificationSummary] = $this->sanitizeItems($handlerResult->items, $tool, $execution);
            $payload = ['items' => $items];
            $this->schemas->assertValid($tool->outputSchema, $payload, 'tool_output');
            $this->assertStringCaps($payload, $tool->maxStringCharacters);
            $encoded = AiCanonicalJson::encode($payload);
            $byteCount = strlen($encoded);

            if ($byteCount > $tool->maxBytes || $byteCount > $execution->reservedBytes) {
                throw new PolicyViolation('tool_byte_budget_exceeded', 'Tool output exceeded its code-owned byte cap.');
            }

            $postScan = $this->dlp->assertSafe($payload, $execution);
            $outputHash = hash('sha256', $encoded);
            $redactionCount = $preScan->redactionCount + $postScan->redactionCount;

            DB::transaction(function () use (
                $call,
                $handlerResult,
                $execution,
                $classificationSummary,
                $outputHash,
                $items,
                $byteCount,
                $queryCount,
                $redactionCount,
                $durationMs,
            ): void {
                $call->update([
                    'output_hash' => $outputHash,
                    'redacted_output_summary' => count($items).' Safe DTO rows; values retained as SHA-256 only.',
                    'row_count' => count($items),
                    'byte_count' => $byteCount,
                    'query_count' => $queryCount,
                    'redaction_count' => $redactionCount,
                    'duration_ms' => $durationMs,
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);

                AiDataAccessLog::query()->create([
                    'ai_agent_run_id' => $execution->runId,
                    'ai_tool_call_id' => $call->id,
                    'dto_type' => count($items) === 1 ? 'tool_safe_dto' : 'tool_safe_dto_collection',
                    'source_type' => $handlerResult->sourceType,
                    'source_id' => $handlerResult->sourceId,
                    'contour' => $execution->contour,
                    'classification_summary' => $classificationSummary,
                    'row_count' => count($items),
                    'byte_count' => $byteCount,
                    'decision' => AiProcessingDecision::Allow,
                    'actor_user_id' => $execution->actorUserId,
                ]);
            }, 3);

            return new AiToolResult(
                'completed',
                $items,
                $outputHash,
                count($items),
                $byteCount,
                $queryCount,
                $redactionCount,
                $durationMs,
            );
        } catch (PolicyViolation $violation) {
            $this->recordFailure($call, $violation, (int) ceil((hrtime(true) - $startedAt) / 1_000_000));

            throw $violation;
        } catch (Throwable) {
            $violation = new PolicyViolation('tool_execution_failed', 'Tool execution failed safely.');
            $this->recordFailure($call, $violation, (int) ceil((hrtime(true) - $startedAt) / 1_000_000));

            throw $violation;
        }
    }

    private function replay(
        AiToolCall $existing,
        AiToolExecutionContext $execution,
        AiToolDefinition $tool,
        string $argumentsHash,
    ): AiToolResult {
        if ($existing->ai_agent_run_id !== $execution->runId
            || $existing->ai_agent_run_step_id !== $execution->runStepId
            || $existing->actor_user_id !== $execution->actorUserId
            || $existing->tool_code !== $tool->code
            || $existing->tool_version !== $tool->version
            || ! hash_equals((string) $existing->workflow_hash, $execution->workflowHash)
            || ! hash_equals($existing->arguments_hash, $argumentsHash)) {
            throw new PolicyViolation('tool_idempotency_conflict', 'Tool idempotency key is bound to another execution.');
        }

        if ($existing->status !== 'completed' || ! is_string($existing->output_hash)) {
            throw new PolicyViolation('tool_duplicate_in_progress', 'Duplicate or previously blocked tool execution cannot run again.');
        }

        return new AiToolResult(
            'completed',
            [],
            $existing->output_hash,
            (int) $existing->row_count,
            (int) $existing->byte_count,
            (int) $existing->query_count,
            (int) $existing->redaction_count,
            (int) $existing->duration_ms,
            true,
        );
    }

    /** @return array{0: AiToolHandlerResult, 1: int} */
    private function runHandler(AiToolDefinition $tool, AiToolExecutionContext $context, array $input): array
    {
        $handler = app($tool->handlerClass);

        if (! $handler instanceof AiToolHandlerInterface) {
            throw new PolicyViolation('tool_handler_invalid', 'Tool handler no longer matches its code-owned contract.');
        }

        $connection = DB::connection();
        $wasLogging = $connection->logging();

        if (! $wasLogging) {
            $connection->flushQueryLog();
            $connection->enableQueryLog();
        }

        $before = count($connection->getQueryLog());

        try {
            $result = $handler->handle($context, $input);
            $queryCount = count($connection->getQueryLog()) - $before;
        } finally {
            if (! $wasLogging) {
                $connection->disableQueryLog();
                $connection->flushQueryLog();
            }
        }

        return [$result, max(0, $queryCount)];
    }

    /** @param list<SafeAiDto> $safeDtos */
    private function sanitizeItems(array $safeDtos, AiToolDefinition $tool, AiToolExecutionContext $execution): array
    {
        $items = [];
        $summary = [];
        $disclosure = new AiDisclosureContext(
            $execution->unitId,
            $execution->unitBusinessContextId,
            $execution->lane,
            $execution->role,
            $execution->audience,
            $execution->purpose,
            $execution->contour->value === 'external_sanitized',
        );

        foreach ($safeDtos as $dto) {
            if (! in_array($dto::class, $tool->outputDtoClasses, true)) {
                throw new PolicyViolation('tool_output_dto_blocked', 'Tool returned a Safe DTO not declared by its code-owned definition.');
            }

            foreach ($dto->fields() as $field => $_value) {
                $rule = $this->classifications->find($dto::class, (string) $field);

                if (! $rule) {
                    throw new PolicyViolation('unclassified_field', 'Unclassified tool output is blocked.');
                }

                if ($rule->classification === DataClassification::Secret
                    || $this->classificationRank($rule->classification) > $this->classificationRank($tool->maximumClassification)
                    || ! in_array($rule->visibilityScope, $tool->allowedVisibilityScopes, true)) {
                    throw new PolicyViolation('tool_classification_ceiling_blocked', 'Tool output exceeds its classification or visibility ceiling.');
                }

                $summary[$rule->classification->value] = ($summary[$rule->classification->value] ?? 0) + 1;
            }

            $items[] = $this->sanitizer->sanitize($dto, $disclosure);
        }

        ksort($summary);

        return [$items, $summary];
    }

    private function assertStringCaps(array $payload, int $maximum): void
    {
        array_walk_recursive($payload, static function (mixed $value) use ($maximum): void {
            if (is_string($value) && mb_strlen($value) > $maximum) {
                throw new PolicyViolation('tool_string_limit_exceeded', 'Tool output string exceeds its code-owned cap.');
            }
        });
    }

    private function recordFailure(AiToolCall $call, PolicyViolation $violation, int $durationMs): void
    {
        $category = match (true) {
            str_contains($violation->errorCode, 'dlp'), str_contains($violation->errorCode, 'untrusted') => 'dlp',
            str_contains($violation->errorCode, 'schema'), str_contains($violation->errorCode, 'field') => 'schema',
            str_contains($violation->errorCode, 'budget'), str_contains($violation->errorCode, 'limit') => 'budget',
            default => 'policy',
        };

        $call->update([
            'authorization_decision' => 'block',
            'duration_ms' => max(0, $durationMs),
            'status' => 'blocked',
            'error_category' => $category,
            'safe_error_code' => mb_substr($violation->errorCode, 0, 96),
            'safe_error_summary' => 'Tool execution was blocked safely.',
            'finished_at' => now(),
        ]);
    }

    private function classificationRank(DataClassification $classification): int
    {
        return match ($classification) {
            DataClassification::Public => 0,
            DataClassification::Internal => 1,
            DataClassification::CommercialConfidential => 2,
            DataClassification::PersonalData => 3,
            DataClassification::Secret => 4,
        };
    }
}
