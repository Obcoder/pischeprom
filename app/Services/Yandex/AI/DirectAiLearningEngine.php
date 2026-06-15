<?php

namespace App\Services\Yandex\AI;

use App\Models\YandexAccount;
use App\Models\YandexDirectAiDecision;

class DirectAiLearningEngine
{
    public function learnFromStats(?YandexAccount $account = null): array
    {
        $evaluated = $this->evaluatePreviousDecisions($account);
        $patterns = $this->detectPatterns($account);

        return [
            'evaluated' => $evaluated,
            'patterns' => $patterns,
            'weights' => $this->updateScoringWeights($patterns),
            'heuristics' => $this->improveHeuristics($patterns),
        ];
    }

    public function evaluatePreviousDecisions(?YandexAccount $account = null): array
    {
        $decisions = YandexDirectAiDecision::query()
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
            ->whereIn('status', [YandexDirectAiDecision::STATUS_EXECUTED, YandexDirectAiDecision::STATUS_APPROVED])
            ->where('created_at', '<=', now()->subDays(3))
            ->with('good.yandexDirectDailyStats')
            ->latest()
            ->limit(50)
            ->get();

        $updated = 0;

        foreach ($decisions as $decision) {
            $stats = $decision->good?->yandexDirectDailyStats
                ? [
                    'clicks' => (int) $decision->good->yandexDirectDailyStats->sum('clicks'),
                    'cost' => round((float) $decision->good->yandexDirectDailyStats->sum('cost'), 2),
                    'conversions' => (int) $decision->good->yandexDirectDailyStats->sum('conversions'),
                ]
                : [];

            $decision->update([
                'reason' => [
                    ...($decision->reason ?: []),
                    'learning' => [
                        'evaluated_at' => now()->toISOString(),
                        'actual_stats' => $stats,
                        'outcome' => $this->outcome($decision, $stats),
                    ],
                ],
            ]);
            $updated++;
        }

        return ['decisions_evaluated' => $updated];
    }

    public function updateScoringWeights(array $patterns = []): array
    {
        return [
            'status' => 'suggested',
            'weights' => [
                'seo_score' => 0.45,
                'direct_readiness' => 0.45,
                'keyword_coverage' => 0.10,
                'performance_confidence_bonus' => data_get($patterns, 'winner_rate', 0) > 0.2 ? 0.05 : 0,
            ],
        ];
    }

    public function detectPatterns(?YandexAccount $account = null): array
    {
        $total = YandexDirectAiDecision::query()
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
            ->count();

        $executed = YandexDirectAiDecision::query()
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
            ->where('status', YandexDirectAiDecision::STATUS_EXECUTED)
            ->count();

        $failed = YandexDirectAiDecision::query()
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
            ->where('status', YandexDirectAiDecision::STATUS_FAILED)
            ->count();

        return [
            'total_decisions' => $total,
            'executed_decisions' => $executed,
            'failed_decisions' => $failed,
            'winner_rate' => $total > 0 ? round($executed / $total, 3) : 0,
            'failure_rate' => $total > 0 ? round($failed / $total, 3) : 0,
        ];
    }

    public function improveHeuristics(array $patterns = []): array
    {
        $failureRate = (float) ($patterns['failure_rate'] ?? 0);

        return [
            'recommendation' => $failureRate > 0.2
                ? 'Increase confidence threshold and keep mode=assist until failure rate drops.'
                : 'Current heuristics are acceptable for monitor/assist mode.',
        ];
    }

    private function outcome(YandexDirectAiDecision $decision, array $stats): string
    {
        if (($stats['conversions'] ?? 0) > 0) {
            return 'positive_signal';
        }

        if (($stats['clicks'] ?? 0) >= (int) config('yandex.direct.ai_autopilot.min_clicks_threshold', 30)) {
            return 'needs_review';
        }

        return 'insufficient_data';
    }
}
