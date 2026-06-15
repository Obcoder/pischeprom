<?php

namespace App\Services\Yandex\AI;

use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectDailyStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DirectAiPerformanceAnalyzer
{
    public function calculateROAS(mixed $target = null, int $days = 14): ?float
    {
        $rows = $this->statsRows($target, $days);
        $cost = (float) $rows->sum('cost');
        $revenue = (float) $rows->sum(fn (YandexDirectDailyStat $row) => (float) data_get($row->raw, 'revenue', 0));

        return $cost > 0 && $revenue > 0 ? round($revenue / $cost, 2) : null;
    }

    public function calculateCPL(mixed $target = null, int $days = 14): ?float
    {
        $rows = $this->statsRows($target, $days);
        $conversions = (int) $rows->sum('conversions');

        return $conversions > 0 ? round((float) $rows->sum('cost') / $conversions, 2) : null;
    }

    public function calculateCTR(mixed $target = null, int $days = 14): ?float
    {
        $rows = $this->statsRows($target, $days);
        $impressions = (int) $rows->sum('impressions');

        return $impressions > 0 ? round((int) $rows->sum('clicks') / $impressions * 100, 2) : null;
    }

    public function summary(mixed $target = null, int $days = 14): array
    {
        $rows = $this->statsRows($target, $days);
        $impressions = (int) $rows->sum('impressions');
        $clicks = (int) $rows->sum('clicks');
        $cost = (float) $rows->sum('cost');
        $conversions = (int) $rows->sum('conversions');

        return [
            'window_days' => $days,
            'rows' => $rows->count(),
            'first_date' => optional($rows->min('date'))?->toDateString(),
            'last_date' => optional($rows->max('date'))?->toDateString(),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'cost' => round($cost, 2),
            'avg_cpc' => $clicks > 0 ? round($cost / $clicks, 2) : null,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : null,
            'conversions' => $conversions,
            'cpl' => $conversions > 0 ? round($cost / $conversions, 2) : null,
            'roas' => $this->calculateROAS($target, $days),
            'days_with_data' => $rows->pluck('date')->map(fn ($date) => optional($date)->toDateString())->filter()->unique()->count(),
        ];
    }

    public function detectWaste(?YandexAccount $account = null, int $days = 14): array
    {
        $minClicks = (int) config('yandex.direct.ai_autopilot.min_clicks_threshold', 30);
        $costLimit = (float) config('yandex.direct.ai_autopilot.waste_cost_limit', 500);
        $ctrFloor = (float) config('yandex.direct.ai_autopilot.low_ctr_threshold', 0.6);

        return $this->aggregateByGood($account, $days)
            ->filter(function (array $row) use ($minClicks, $costLimit, $ctrFloor) {
                return $row['clicks'] >= $minClicks
                    && $row['conversions'] === 0
                    && ($row['cost'] >= $costLimit || ($row['ctr'] !== null && $row['ctr'] < $ctrFloor));
            })
            ->take(50)
            ->values()
            ->all();
    }

    public function detectWinningAds(?YandexAccount $account = null, int $days = 14): array
    {
        $targetCpl = (float) config('yandex.direct.ai_autopilot.target_cpl', 700);
        $highCtr = (float) config('yandex.direct.ai_autopilot.high_ctr_threshold', 2.5);

        return $this->aggregateByAd($account, $days)
            ->filter(function (array $row) use ($targetCpl, $highCtr) {
                return $row['conversions'] >= 2
                    && ($row['cpl'] === null || $row['cpl'] <= $targetCpl)
                    && ($row['ctr'] === null || $row['ctr'] >= $highCtr);
            })
            ->take(50)
            ->values()
            ->all();
    }

    public function detectUnderperformingAds(?YandexAccount $account = null, int $days = 14): array
    {
        $minImpressions = (int) config('yandex.direct.ai_autopilot.min_impressions_threshold', 1000);
        $lowCtr = (float) config('yandex.direct.ai_autopilot.low_ctr_threshold', 0.6);

        return $this->aggregateByAd($account, $days)
            ->filter(function (array $row) use ($minImpressions, $lowCtr) {
                return $row['impressions'] >= $minImpressions
                    && ($row['clicks'] === 0 || ($row['ctr'] !== null && $row['ctr'] < $lowCtr));
            })
            ->take(50)
            ->values()
            ->all();
    }

    public function aggregateByGood(?YandexAccount $account = null, int $days = 14): Collection
    {
        return $this->aggregateQuery($account, $days)
            ->whereNotNull('good_id')
            ->groupBy('good_id')
            ->selectRaw('good_id, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(cost) as cost, SUM(conversions) as conversions')
            ->with('good:id,name,slug,ava_thumb')
            ->get()
            ->map(fn (YandexDirectDailyStat $row) => $this->formatAggregateRow($row));
    }

    public function aggregateByAd(?YandexAccount $account = null, int $days = 14): Collection
    {
        return $this->aggregateQuery($account, $days)
            ->whereNotNull('ad_id')
            ->groupBy('ad_id', 'good_id')
            ->selectRaw('ad_id, good_id, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(cost) as cost, SUM(conversions) as conversions')
            ->with(['good:id,name,slug,ava_thumb', 'ad:id,title_1,status'])
            ->get()
            ->map(function (YandexDirectDailyStat $row) {
                return [
                    ...$this->formatAggregateRow($row),
                    'ad_id' => $row->ad_id,
                    'ad' => $row->ad,
                ];
            });
    }

    private function statsRows(mixed $target = null, int $days = 14): Collection
    {
        if ($target instanceof Collection) {
            return $target;
        }

        $query = YandexDirectDailyStat::query()
            ->whereDate('date', '>=', now()->subDays($days - 1)->toDateString());

        if ($target instanceof Good) {
            $query->where('good_id', $target->id);
        } elseif ($target instanceof YandexAccount) {
            $query->where('yandex_account_id', $target->id);
        } elseif ($target instanceof YandexDirectAd) {
            $query->where('ad_id', $target->id);
        } elseif (is_array($target)) {
            foreach (['good_id', 'yandex_account_id', 'campaign_id', 'ad_group_id', 'ad_id', 'keyword_id'] as $field) {
                if (array_key_exists($field, $target) && filled($target[$field])) {
                    $query->where($field, $target[$field]);
                }
            }
        }

        return $query->get();
    }

    private function aggregateQuery(?YandexAccount $account, int $days): Builder
    {
        return YandexDirectDailyStat::query()
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
            ->whereDate('date', '>=', now()->subDays($days - 1)->toDateString());
    }

    private function formatAggregateRow(YandexDirectDailyStat $row): array
    {
        $impressions = (int) $row->impressions;
        $clicks = (int) $row->clicks;
        $cost = (float) $row->cost;
        $conversions = (int) $row->conversions;

        return [
            'good_id' => $row->good_id,
            'good' => $row->good,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'cost' => round($cost, 2),
            'conversions' => $conversions,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : null,
            'avg_cpc' => $clicks > 0 ? round($cost / $clicks, 2) : null,
            'cpl' => $conversions > 0 ? round($cost / $conversions, 2) : null,
        ];
    }
}
