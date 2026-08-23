<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Models\AiAgentRun;

class CompleteAiAgentRun
{
    public function __construct(private readonly AiAgentRunStateMachine $state) {}

    public function handle(AiAgentRun $run, AiProviderUsage $usage): AiAgentRun
    {
        return $this->state->transition($run, AiRunStatus::Completed, [
            'accumulated_tokens' => $run->accumulated_tokens + $usage->totalTokens(),
            'accumulated_searches' => $run->accumulated_searches + $usage->searchCount,
            'accumulated_cost_rub' => number_format(
                (float) $run->accumulated_cost_rub + (float) $usage->normalizedRubAmount,
                4,
                '.',
                '',
            ),
            'current_step' => $run->current_step + 1,
            'completed_at' => now(),
            'safe_error_code' => null,
            'safe_error_summary' => null,
        ]);
    }
}
