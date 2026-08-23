<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\DTO\Runs\CreateAiAgentRunResult;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Domain\AiSales\Services\AiPromptSchemaRegistry;
use App\Domain\AiSales\Services\AiStage04FeatureGuard;
use App\Domain\AiSales\Services\AiTaskProfileRegistry;
use App\Models\AiAgentDefinition;
use App\Models\AiAgentRun;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAiAgentRun
{
    public function __construct(
        private readonly AiControlPlaneAuthorizationService $authorization,
        private readonly AiStage04FeatureGuard $features,
        private readonly AiTaskProfileRegistry $taskProfiles,
        private readonly AiPromptSchemaRegistry $prompts,
    ) {}

    public function handle(
        User $actor,
        Unit $unit,
        UnitBusinessContext $context,
        AiAgentDefinition $definition,
        string $callerIdempotencyKey,
    ): CreateAiAgentRunResult {
        if (! $definition->enabled) {
            throw new PolicyViolation('agent_definition_disabled', 'The selected synthetic agent definition is disabled.');
        }

        if (! $this->authorization->canRun($actor, $unit, $context)) {
            throw new PolicyViolation('run_creation_forbidden', 'Actor cannot run AI for the requested Unit context.');
        }

        $taskContour = $this->taskProfiles->contour($definition->default_task_profile);
        $allowedContours = array_values((array) $definition->allowed_contours);

        if (count($allowedContours) !== 1 || $allowedContours[0] !== $taskContour->value) {
            throw new PolicyViolation('definition_contour_mismatch', 'Agent definition must bind exactly one code-owned processing contour.');
        }

        if (! in_array($context->lane->value, (array) $definition->allowed_lanes, true)
            || ! in_array($definition->default_purpose->value, (array) $definition->allowed_purposes, true)
            || ! in_array($definition->default_audience->value, (array) $definition->allowed_audiences, true)) {
            throw new PolicyViolation('definition_context_not_allowed', 'Agent definition does not allow this purpose/audience/lane.');
        }

        $this->features->assertEnabled($taskContour);
        $prompt = $this->prompts->get($definition);
        $idempotencyKey = hash('sha256', $actor->id.':'.$callerIdempotencyKey);
        $existing = AiAgentRun::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            if ((int) $existing->unit_id !== (int) $unit->id
                || (int) $existing->unit_business_context_id !== (int) $context->id
                || (int) $existing->ai_agent_definition_id !== (int) $definition->id) {
                throw new PolicyViolation('idempotency_key_conflict', 'Idempotency key is already bound to another run subject.');
            }

            return new CreateAiAgentRunResult($existing, false);
        }

        $definitionLimits = (array) $definition->default_limits;
        $maxSteps = $this->boundedInt($definitionLimits['max_steps'] ?? null, 'max_steps', 1, 20);
        $maxSearches = $this->boundedInt($definitionLimits['max_searches'] ?? 0, 'max_searches', 0, 20);
        $maxTokens = $this->boundedInt($definitionLimits['max_tokens'] ?? null, 'max_tokens', 1, 100_000);
        $maxCost = number_format(min(
            max(0, (float) ($definitionLimits['max_cost_rub'] ?? 0)),
            max(0, (float) config('ai-sales.limits.max_cost_rub', 0)),
        ), 4, '.', '');

        return DB::transaction(function () use (
            $actor,
            $unit,
            $context,
            $definition,
            $taskContour,
            $prompt,
            $idempotencyKey,
            $maxSteps,
            $maxSearches,
            $maxTokens,
            $maxCost,
        ): CreateAiAgentRunResult {
            $run = AiAgentRun::query()->create([
                'public_id' => (string) Str::uuid(),
                'ai_agent_definition_id' => $definition->id,
                'definition_code' => $definition->code,
                'definition_version' => $definition->version,
                'initiator_user_id' => $actor->id,
                'unit_id' => $unit->id,
                'unit_name_snapshot' => mb_substr($unit->name, 0, 255),
                'unit_business_context_id' => $context->id,
                'unit_context_snapshot' => [
                    'unit_id' => $unit->id,
                    'context_id' => $context->id,
                    'lane' => $context->lane->value,
                    'role_code' => $context->role_code->value,
                    'stage' => $context->stage->value,
                    'status' => $context->status->value,
                ],
                'purpose' => $definition->default_purpose,
                'audience' => $definition->default_audience,
                'lane' => $context->lane,
                'role_code' => $context->role_code,
                'task_profile' => $definition->default_task_profile,
                'requested_contour' => $taskContour,
                'selected_contour' => null,
                'provider_route_preference' => $taskContour->value,
                'model_profile_preference' => $definition->default_model_profile,
                'status' => AiRunStatus::Queued,
                'prompt_hash' => $prompt['prompt_hash'],
                'schema_hash' => $prompt['schema_hash'],
                'max_steps' => $maxSteps,
                'max_searches' => $maxSearches,
                'max_tokens' => $maxTokens,
                'max_cost_rub' => $maxCost,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => (string) Str::uuid(),
                'queued_at' => now(),
                'expires_at' => now()->addMinutes(30),
            ]);

            return new CreateAiAgentRunResult($run, true);
        }, 3);
    }

    private function boundedInt(mixed $value, string $key, int $minimum, int $maximum): int
    {
        $configured = (int) config("ai-sales.limits.{$key}", $maximum);
        $requested = (int) $value;

        if ($requested < $minimum || $configured < $minimum) {
            throw new PolicyViolation('invalid_run_limit', "Run limit {$key} is not safely configured.");
        }

        return min($requested, $configured, $maximum);
    }
}
