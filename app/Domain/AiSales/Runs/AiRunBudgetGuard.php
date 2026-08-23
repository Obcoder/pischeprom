<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentRun;
use App\Models\AiUsageRecord;

class AiRunBudgetGuard
{
    public function assertBeforeProviderCall(AiAgentRun $run): void
    {
        if ($run->current_step >= $run->max_steps) {
            throw new PolicyViolation('run_step_budget_exceeded', 'AI run step budget is exhausted.');
        }

        if ($run->accumulated_tokens >= $run->max_tokens) {
            throw new PolicyViolation('run_token_budget_exceeded', 'AI run token budget is exhausted.');
        }

        if ($this->moneyUnits($run->accumulated_cost_rub) > $this->moneyUnits($run->max_cost_rub)) {
            throw new PolicyViolation('run_cost_budget_exceeded', 'AI run RUB budget is exhausted.');
        }

        $this->assertAggregateLimit('global_daily_rub', AiUsageRecord::query()->whereDate('created_at', today())->sum('normalized_rub_amount'));
        $this->assertAggregateLimit('global_monthly_rub', AiUsageRecord::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('normalized_rub_amount'));
        $this->assertAggregateLimit(
            $run->requested_contour->value.'_daily_rub',
            AiUsageRecord::query()->where('contour', $run->requested_contour->value)->whereDate('created_at', today())->sum('normalized_rub_amount'),
        );
        $this->assertRunDimensionLimit('per_agent_daily_rub', 'ai_agent_definition_id', $run->ai_agent_definition_id);
        $this->assertRunDimensionLimit('per_task_profile_daily_rub', 'task_profile', $run->task_profile->value);
        $this->assertRunDimensionLimit('per_unit_daily_rub', 'unit_id', $run->unit_id);
        $this->assertRunDimensionLimit('per_context_daily_rub', 'unit_business_context_id', $run->unit_business_context_id);
    }

    public function assertUsageFits(AiAgentRun $run, AiProviderUsage $usage): void
    {
        if ($run->accumulated_tokens + $usage->totalTokens() > $run->max_tokens) {
            throw new PolicyViolation('run_token_budget_exceeded', 'Provider usage exceeds the run token budget.');
        }

        $nextCost = $this->moneyUnits($run->accumulated_cost_rub) + $this->moneyUnits($usage->normalizedRubAmount);

        if ($nextCost > $this->moneyUnits($run->max_cost_rub)) {
            throw new PolicyViolation('run_cost_budget_exceeded', 'Provider usage exceeds the run RUB budget.');
        }

        if ((int) $usage->outputTokens > (int) config('ai-sales.limits.max_output_tokens', 0)) {
            throw new PolicyViolation('run_output_token_budget_exceeded', 'Provider output exceeds the configured token cap.');
        }

        if ($run->accumulated_searches + $usage->searchCount > $run->max_searches) {
            throw new PolicyViolation('run_search_budget_exceeded', 'Provider usage exceeds the run search budget.');
        }

        if ($usage->toolCallCount > (int) config('ai-sales.limits.max_tool_calls', 0)) {
            throw new PolicyViolation('run_tool_call_budget_exceeded', 'Provider usage exceeds the tool-call cap.');
        }
    }

    private function assertAggregateLimit(string $key, mixed $spent): void
    {
        $limit = config("ai-sales.limits.{$key}");

        if ($limit === null) {
            throw new PolicyViolation('budget_not_configured', 'Required AI budget is not configured.');
        }

        if ($this->moneyUnits($spent) > $this->moneyUnits($limit)) {
            throw new PolicyViolation('aggregate_budget_exceeded', 'AI aggregate RUB budget is exhausted.');
        }
    }

    private function assertRunDimensionLimit(string $limitKey, string $column, mixed $value): void
    {
        $spent = AiUsageRecord::query()
            ->whereDate('created_at', today())
            ->whereHas('run', fn ($query) => $query->where($column, $value))
            ->sum('normalized_rub_amount');

        $this->assertAggregateLimit($limitKey, $spent);
    }

    private function moneyUnits(mixed $amount): int
    {
        return (int) round(((float) $amount) * 10_000);
    }
}
