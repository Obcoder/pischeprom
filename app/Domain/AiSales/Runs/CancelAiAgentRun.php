<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Models\AiAgentRun;

class CancelAiAgentRun
{
    public function __construct(private readonly AiAgentRunStateMachine $state) {}

    public function handle(AiAgentRun $run): AiAgentRun
    {
        if ($run->status->terminal()) {
            return $run->fresh();
        }

        $run->steps()
            ->whereNotIn('status', [AiRunStepStatus::Completed->value, AiRunStepStatus::Failed->value])
            ->update([
                'status' => AiRunStepStatus::Cancelled->value,
                'safe_error_code' => 'run_cancelled',
                'safe_error_summary' => 'Run cancelled by an authorized human.',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->state->transition($run, AiRunStatus::Cancelled, [
            'cancelled_at' => now(),
            'completed_at' => now(),
            'safe_error_code' => 'run_cancelled',
            'safe_error_summary' => 'Run cancelled by an authorized human.',
        ]);
    }
}
