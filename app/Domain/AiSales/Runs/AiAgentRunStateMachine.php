<?php

namespace App\Domain\AiSales\Runs;

use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentRun;
use Illuminate\Support\Facades\DB;

class AiAgentRunStateMachine
{
    private const TRANSITIONS = [
        'queued' => ['preparing', 'cancelled', 'blocked_by_policy', 'blocked_by_contour'],
        'preparing' => ['policy_check', 'cancelled', 'failed', 'blocked_by_policy', 'blocked_by_contour'],
        'policy_check' => ['ready', 'cancelled', 'blocked_by_policy', 'blocked_by_dlp', 'blocked_by_contour', 'residency_unverified', 'failed'],
        'ready' => ['sent', 'cancelled', 'budget_exceeded', 'blocked_by_policy', 'blocked_by_dlp', 'blocked_by_contour', 'residency_unverified', 'provider_unavailable', 'failed'],
        'sent' => ['processing', 'cancelled', 'blocked_by_policy', 'blocked_by_dlp', 'blocked_by_contour', 'provider_unavailable', 'failed'],
        'processing' => ['completed', 'requires_action', 'cancelled', 'budget_exceeded', 'blocked_by_policy', 'blocked_by_dlp', 'blocked_by_contour', 'provider_unavailable', 'failed'],
        'requires_action' => ['processing', 'cancelled', 'budget_exceeded', 'blocked_by_policy', 'failed'],
    ];

    public function transition(AiAgentRun $run, AiRunStatus $to, array $attributes = []): AiAgentRun
    {
        return DB::transaction(function () use ($run, $to, $attributes): AiAgentRun {
            $locked = AiAgentRun::query()->lockForUpdate()->findOrFail($run->id);
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if ($from->terminal() || ! in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true)) {
                throw new PolicyViolation(
                    'invalid_run_transition',
                    "AI run transition {$from->value} -> {$to->value} is not allowed.",
                );
            }

            $locked->fill([
                ...$attributes,
                'status' => $to,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked->fresh();
        }, 3);
    }
}
