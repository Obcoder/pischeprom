<?php

namespace App\Services\Yandex\AI;

use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectAiDecision;
use App\Models\YandexSyncLog;
use App\Services\Yandex\DirectAutoLauncherService;
use Illuminate\Support\Collection;
use Throwable;

class DirectAiAutopilotEngine
{
    public function __construct(
        private readonly DirectAiDecisionEngine $decisionEngine,
        private readonly DirectAiSafetyGateService $safetyGate,
        private readonly DirectAiLearningEngine $learningEngine,
        private readonly DirectAiPerformanceAnalyzer $performanceAnalyzer,
        private readonly DirectAiBudgetOptimizer $budgetOptimizer,
        private readonly DirectAutoLauncherService $autoLauncher,
    ) {}

    public function run(YandexAccount $account): array
    {
        return $this->processCycle($account);
    }

    public function processCycle(?YandexAccount $account = null): array
    {
        $accounts = $account ? collect([$account]) : $this->activeAccounts();
        $result = [
            'mode' => (string) config('yandex.direct.ai_autopilot.mode', 'monitor'),
            'accounts' => $accounts->count(),
            'goods_analyzed' => 0,
            'decisions_created' => 0,
            'decisions_executed' => 0,
            'decisions_rejected' => 0,
            'learning' => [],
            'budget' => [],
        ];

        foreach ($accounts as $item) {
            $cycle = [
                'account_id' => $item->id,
                'started_at' => now()->toISOString(),
            ];

            try {
                $goods = $this->analyzeGoods($item);
                $campaignHealth = $this->analyzeCampaigns($item);
                $decisions = $this->generateDecisions($item, $goods);
                $applied = $this->applySafeActions($decisions);
                $learning = $this->learningEngine->learnFromStats($item);
                $budget = $this->budgetOptimizer->redistributeBudget($item);

                $result['goods_analyzed'] += $goods->count();
                $result['decisions_created'] += $decisions->count();
                $result['decisions_executed'] += $applied['executed'];
                $result['decisions_rejected'] += $applied['rejected'];
                $result['learning'][$item->id] = $learning;
                $result['budget'][$item->id] = $budget;

                $cycle = [
                    ...$cycle,
                    'finished_at' => now()->toISOString(),
                    'goods_analyzed' => $goods->count(),
                    'campaign_health' => $campaignHealth,
                    'decisions_created' => $decisions->count(),
                    'applied' => $applied,
                    'next_cycle' => $this->scheduleNextCycle(),
                ];

                $this->log($item, 'direct.ai_autopilot.cycle', 'success', $cycle);
            } catch (Throwable $e) {
                $this->log($item, 'direct.ai_autopilot.cycle', 'error', $cycle, $e->getMessage());
                $result['errors'][] = [
                    'account_id' => $item->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    public function analyzeGoods(YandexAccount $account): Collection
    {
        $limit = (int) config('yandex.direct.ai_autopilot.max_goods_per_cycle', 50);

        return Good::query()
            ->with(['seo', 'products.category', 'yandexDirectAds', 'yandexDirectKeywords', 'yandexDirectDailyStats'])
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereHas('seo')
                    ->orWhereHas('yandexDirectDailyStats')
                    ->orWhereHas('yandexDirectAds');
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function analyzeCampaigns(?YandexAccount $account = null): array
    {
        return [
            'health_window_days' => (int) config('yandex.direct.ai_autopilot.learning_window_days', 14),
            'waste' => $this->performanceAnalyzer->detectWaste($account),
            'winners' => $this->performanceAnalyzer->detectWinningAds($account),
            'underperforming_ads' => $this->performanceAnalyzer->detectUnderperformingAds($account),
        ];
    }

    public function generateDecisions(YandexAccount $account, ?Collection $goods = null): Collection
    {
        $goods ??= $this->analyzeGoods($account);
        $created = collect();

        foreach ($goods as $good) {
            foreach ($this->decisionEngine->decisionsForGood($good) as $payload) {
                if ($this->hasRecentDecision($account, $good, $payload['type'])) {
                    continue;
                }

                $decision = YandexDirectAiDecision::query()->create([
                    'yandex_account_id' => $account->id,
                    'good_id' => $good->id,
                    'type' => $payload['type'],
                    'confidence_score' => $payload['confidence_score'],
                    'expected_impact' => $payload['expected_impact'],
                    'risk_level' => $payload['risk_level'],
                    'status' => YandexDirectAiDecision::STATUS_PENDING,
                    'reason' => $payload['reason'],
                    'signals' => $payload['signals'],
                ]);

                $safety = $this->safetyGate->inspect($decision);
                if (!($safety['ok'] ?? false)) {
                    $decision->update([
                        'status' => YandexDirectAiDecision::STATUS_REJECTED,
                        'reason' => [
                            ...($decision->reason ?: []),
                            'safety' => $safety,
                        ],
                    ]);
                } elseif (!empty($safety['warnings'])) {
                    $decision->update([
                        'reason' => [
                            ...($decision->reason ?: []),
                            'safety' => $safety,
                        ],
                    ]);
                }

                $created->push($decision->fresh(['good', 'account']));
            }
        }

        return $created;
    }

    public function applySafeActions(?Collection $decisions = null): array
    {
        $mode = (string) config('yandex.direct.ai_autopilot.mode', 'monitor');
        $decisions ??= YandexDirectAiDecision::query()
            ->where('status', YandexDirectAiDecision::STATUS_PENDING)
            ->latest()
            ->limit(50)
            ->get();

        $result = [
            'mode' => $mode,
            'checked' => 0,
            'approved' => 0,
            'executed' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        foreach ($decisions as $decision) {
            $decision = $decision->fresh(['good.seo', 'account']);
            if (!$decision || $decision->status !== YandexDirectAiDecision::STATUS_PENDING) {
                continue;
            }

            $result['checked']++;
            $safety = $this->safetyGate->inspect($decision, $mode);

            if (!($safety['ok'] ?? false)) {
                $decision->update([
                    'status' => YandexDirectAiDecision::STATUS_REJECTED,
                    'reason' => [
                        ...($decision->reason ?: []),
                        'safety' => $safety,
                    ],
                ]);
                $result['rejected']++;
                continue;
            }

            if (!($safety['executable'] ?? false)) {
                $decision->update([
                    'reason' => [
                        ...($decision->reason ?: []),
                        'safety' => $safety,
                    ],
                ]);
                continue;
            }

            $decision->update([
                'status' => YandexDirectAiDecision::STATUS_APPROVED,
                'reason' => [
                    ...($decision->reason ?: []),
                    'safety' => $safety,
                    'auto_approved_at' => now()->toISOString(),
                ],
            ]);
            $result['approved']++;

            try {
                $execution = $this->executeDecision($decision);
                $decision->update([
                    'status' => YandexDirectAiDecision::STATUS_EXECUTED,
                    'executed_at' => now(),
                    'reason' => [
                        ...($decision->reason ?: []),
                        'execution' => $execution,
                    ],
                ]);
                $result['executed']++;
            } catch (Throwable $e) {
                $decision->update([
                    'status' => YandexDirectAiDecision::STATUS_FAILED,
                    'reason' => [
                        ...($decision->reason ?: []),
                        'execution_error' => $e->getMessage(),
                    ],
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    public function scheduleNextCycle(): array
    {
        $minutes = (int) config('yandex.direct.ai_autopilot.cycle_minutes', 60);

        return [
            'cycle_minutes' => $minutes,
            'next_run_after' => now()->addMinutes($minutes)->toISOString(),
        ];
    }

    private function executeDecision(YandexDirectAiDecision $decision): array
    {
        if ($decision->type !== YandexDirectAiDecision::TYPE_LAUNCH || !$decision->good || !$decision->account) {
            return [
                'status' => 'skipped',
                'message' => 'MVP executes only safe launch dry-run decisions.',
            ];
        }

        return $this->autoLauncher->launch($decision->good, $decision->account, [
            'dry_run' => true,
            'budget_approved' => false,
            'daily_budget' => (float) data_get($decision->expected_impact, 'daily_budget', config('yandex.direct.auto_launch.daily_budget', 300)),
            'source' => 'ai_autopilot',
            'ai_decision_id' => $decision->id,
        ]);
    }

    private function activeAccounts(): Collection
    {
        return YandexAccount::query()
            ->where('is_active', true)
            ->whereNotNull('access_token')
            ->get();
    }

    private function hasRecentDecision(YandexAccount $account, Good $good, string $type): bool
    {
        $hours = (int) config('yandex.direct.ai_autopilot.cooldown_hours', 24);

        return YandexDirectAiDecision::query()
            ->where('yandex_account_id', $account->id)
            ->where('good_id', $good->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereIn('status', [
                YandexDirectAiDecision::STATUS_PENDING,
                YandexDirectAiDecision::STATUS_APPROVED,
                YandexDirectAiDecision::STATUS_EXECUTED,
                YandexDirectAiDecision::STATUS_REJECTED,
            ])
            ->exists();
    }

    private function log(YandexAccount $account, string $action, string $status, array $payload, ?string $error = null): void
    {
        YandexSyncLog::query()->create([
            'yandex_account_id' => $account->id,
            'entity_type' => 'direct_ai_autopilot',
            'action' => $action,
            'status' => $status,
            'request_payload' => ['mode' => config('yandex.direct.ai_autopilot.mode', 'monitor')],
            'response_payload' => $payload,
            'error_message' => $error,
        ]);
    }
}
