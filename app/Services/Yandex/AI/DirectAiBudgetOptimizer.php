<?php

namespace App\Services\Yandex\AI;

use App\Models\YandexAccount;
use App\Models\YandexDirectAiDecision;
use App\Models\YandexDirectCampaign;

class DirectAiBudgetOptimizer
{
    public function __construct(private readonly DirectAiPerformanceAnalyzer $performanceAnalyzer) {}

    public function allocateBudget(?YandexAccount $account = null): array
    {
        $dailyBudget = (float) config('yandex.direct.auto_launch.daily_budget', 300);
        $maxDailyBudget = (float) config('yandex.direct.auto_launch.max_daily_budget', 500);

        return [
            'mode' => (string) config('yandex.direct.ai_autopilot.mode', 'monitor'),
            'default_daily_budget' => min($dailyBudget, $maxDailyBudget),
            'max_daily_budget' => $maxDailyBudget,
            'guard' => 'No automatic budget increase is performed in MVP.',
            'account_id' => $account?->id,
        ];
    }

    public function redistributeBudget(?YandexAccount $account = null): array
    {
        return [
            'winners' => $this->performanceAnalyzer->detectWinningAds($account),
            'waste' => $this->performanceAnalyzer->detectWaste($account),
            'recommendation' => 'Move budget manually from waste rows to winners after manager approval.',
        ];
    }

    public function throttleCampaign(YandexDirectCampaign $campaign, ?string $reason = null): array
    {
        return [
            'campaign_id' => $campaign->id,
            'action' => 'throttle_campaign',
            'status' => 'suggested_only',
            'reason' => $reason ?: 'Budget throttling requires explicit manual approval.',
        ];
    }

    public function boostCampaign(YandexDirectCampaign $campaign, ?string $reason = null): array
    {
        $increase = (int) config('yandex.direct.ai_autopilot.max_budget_increase_percent', 20);

        return [
            'campaign_id' => $campaign->id,
            'action' => 'boost_campaign',
            'status' => 'blocked_by_mvp_guard',
            'max_increase_percent' => $increase,
            'reason' => $reason ?: 'Auto budget increase is disabled in MVP.',
        ];
    }

    public function freezeWasteCampaigns(?YandexAccount $account = null): array
    {
        return [
            'action' => 'freeze_waste_campaigns',
            'status' => 'suggested_only',
            'items' => $this->performanceAnalyzer->detectWaste($account),
        ];
    }
}
