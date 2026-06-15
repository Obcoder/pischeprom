<?php

namespace App\Services\Yandex\AI;

use App\Models\Good;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectAiDecision;

class DirectAiDecisionEngine
{
    public function __construct(private readonly DirectAiSignalEngine $signals) {}

    public function decisionsForGood(Good $good): array
    {
        return collect([
            $this->decideLaunch($good),
            $this->decidePause($good),
            $this->decideScale($good),
            $this->decideOptimize($good),
            $this->decideKeywordExpansion($good),
            $this->decideNegativeKeywords($good),
        ])->filter()->values()->all();
    }

    public function decideLaunch(Good $good): ?array
    {
        $signals = $this->signals->collectSignals($good);
        $seoScore = (int) data_get($signals, 'seo.score', 0);
        $readiness = (int) data_get($signals, 'direct.readiness_score', 0);
        $keywordCount = (int) data_get($signals, 'direct.keyword_count', 0);
        $hasBlockingAd = in_array(data_get($signals, 'direct.latest_ad_status'), [
            YandexDirectAd::STATUS_SYNCING,
            YandexDirectAd::STATUS_SENT,
            YandexDirectAd::STATUS_MODERATION,
            YandexDirectAd::STATUS_ACTIVE,
        ], true);

        if (
            $seoScore <= 75
            || $readiness <= 70
            || $keywordCount < 1
            || !data_get($signals, 'good.has_image')
            || data_get($signals, 'direct.has_external_campaign')
            || $hasBlockingAd
        ) {
            return null;
        }

        $confidence = min(92, (int) round(($seoScore * 0.45) + ($readiness * 0.45) + min(10, $keywordCount / 4)));

        return $this->decision(
            YandexDirectAiDecision::TYPE_LAUNCH,
            $confidence,
            YandexDirectAiDecision::RISK_LOW,
            [
                'suggested_action' => 'create_campaign_suggestion',
                'daily_budget' => (float) config('yandex.direct.auto_launch.daily_budget', 300),
                'expected' => 'Получить первые управляемые показы и клики по готовому SEO-товару.',
            ],
            [
                'why' => 'Товар готов к запуску: SEO и Direct readiness выше порогов, есть ключи и изображение, активной рекламы нет.',
                'rules' => [
                    'seo_score > 75',
                    'direct_readiness > 70',
                    'no_active_direct_campaign',
                    'keyword_count > 0',
                    'has_image',
                ],
            ],
            $signals,
        );
    }

    public function decidePause(Good $good): ?array
    {
        $signals = $this->signals->collectSignals($good);
        $clicks = (int) data_get($signals, 'stats.clicks', 0);
        $cost = (float) data_get($signals, 'stats.cost', 0);
        $conversions = (int) data_get($signals, 'stats.conversions', 0);
        $ctr = data_get($signals, 'stats.ctr');
        $bounce = data_get($signals, 'landing_behavior.bounce_rate');
        $costLimit = (float) config('yandex.direct.ai_autopilot.waste_cost_limit', 500);
        $lowCtr = (float) config('yandex.direct.ai_autopilot.low_ctr_threshold', 0.6);
        $highBounce = (float) config('yandex.direct.ai_autopilot.high_bounce_threshold', 75);

        if ($clicks <= 30 || $cost <= $costLimit || $conversions > 0) {
            return null;
        }

        if (($ctr === null || $ctr >= $lowCtr) && ($bounce === null || $bounce < $highBounce)) {
            return null;
        }

        return $this->decision(
            YandexDirectAiDecision::TYPE_PAUSE,
            min(88, 55 + (int) min(25, $clicks / 2) + (int) min(8, $cost / 300)),
            YandexDirectAiDecision::RISK_MEDIUM,
            [
                'suggested_action' => 'pause_campaign_or_ads',
                'expected' => 'Остановить расход без заявок и освободить бюджет для лучших товаров.',
                'protected_budget' => round($cost, 2),
            ],
            [
                'why' => 'Есть достаточный объём кликов и расход, но конверсий нет; качество трафика выглядит слабым.',
                'rules' => ['clicks > 30', 'cost > limit', 'conversions = 0', 'low_ctr_or_high_bounce'],
            ],
            $signals,
        );
    }

    public function decideScale(Good $good): ?array
    {
        $signals = $this->signals->collectSignals($good);
        $conversions = (int) data_get($signals, 'stats.conversions', 0);
        $cpl = data_get($signals, 'stats.cpl');
        $ctr = data_get($signals, 'stats.ctr');
        $clicks = (int) data_get($signals, 'stats.clicks', 0);
        $targetCpl = (float) config('yandex.direct.ai_autopilot.target_cpl', 700);
        $highCtr = (float) config('yandex.direct.ai_autopilot.high_ctr_threshold', 2.5);

        if ($conversions < 2 || $cpl === null || $cpl > $targetCpl || $ctr === null || $ctr < $highCtr || $clicks < 30) {
            return null;
        }

        return $this->decision(
            YandexDirectAiDecision::TYPE_SCALE,
            min(90, 62 + (int) min(18, $conversions * 4) + (int) min(10, $ctr)),
            YandexDirectAiDecision::RISK_MEDIUM,
            [
                'suggested_action' => 'increase_budget_with_guard',
                'max_budget_increase_percent' => (int) config('yandex.direct.ai_autopilot.max_budget_increase_percent', 20),
                'expected' => 'Аккуратно увеличить трафик на товаре с конверсиями и CPL ниже цели.',
            ],
            [
                'why' => 'Товар показывает стабильные конверсии, CPL ниже целевого и высокий CTR.',
                'rules' => ['conversions >= 2', 'cpl <= target', 'ctr >= high_threshold', 'stable_clicks'],
            ],
            $signals,
        );
    }

    public function decideOptimize(Good $good): ?array
    {
        $signals = $this->signals->collectSignals($good);
        $impressions = (int) data_get($signals, 'stats.impressions', 0);
        $clicks = (int) data_get($signals, 'stats.clicks', 0);
        $ctr = data_get($signals, 'stats.ctr');
        $avgCpc = data_get($signals, 'stats.avg_cpc');
        $lowCtr = (float) config('yandex.direct.ai_autopilot.low_ctr_threshold', 0.6);
        $highCpc = (float) config('yandex.direct.ai_autopilot.high_cpc_threshold', 80);

        $badCtr = $impressions >= 500 && ($clicks === 0 || ($ctr !== null && $ctr < $lowCtr));
        $badCpc = $avgCpc !== null && $avgCpc >= $highCpc;

        if (!$badCtr && !$badCpc) {
            return null;
        }

        return $this->decision(
            YandexDirectAiDecision::TYPE_OPTIMIZE,
            $badCtr && $badCpc ? 82 : 68,
            YandexDirectAiDecision::RISK_LOW,
            [
                'suggested_action' => 'rewrite_ads_or_split_keywords',
                'expected' => 'Улучшить CTR/CPC без увеличения бюджета.',
            ],
            [
                'why' => 'Показы есть, но кликов мало, CTR низкий или CPC слишком высокий.',
                'rules' => ['impressions_with_low_clicks', 'low_ctr_or_high_cpc'],
            ],
            $signals,
        );
    }

    public function decideKeywordExpansion(Good $good): ?array
    {
        $signals = $this->signals->collectSignals($good);
        $keywordCount = (int) data_get($signals, 'direct.keyword_count', 0);
        $conversions = (int) data_get($signals, 'stats.conversions', 0);
        $ctr = data_get($signals, 'stats.ctr');

        if ($keywordCount >= 30 || $conversions < 1 || ($ctr !== null && $ctr < 1.2)) {
            return null;
        }

        return $this->decision(
            YandexDirectAiDecision::TYPE_KEYWORD_EXPANSION,
            64,
            YandexDirectAiDecision::RISK_LOW,
            [
                'suggested_action' => 'expand_keywords_from_search_queries',
                'expected' => 'Расширить семантику вокруг уже конвертирующего товара.',
            ],
            [
                'why' => 'Есть конверсии, но семантическое покрытие ограничено.',
                'rules' => ['conversions > 0', 'keyword_count < 30', 'ctr_not_low'],
            ],
            $signals,
        );
    }

    public function decideNegativeKeywords(Good $good): ?array
    {
        $signals = $this->signals->collectSignals($good);
        $anomalies = collect(data_get($signals, 'anomalies', []));
        $negativeCount = (int) data_get($signals, 'direct.negative_keyword_count', 0);

        if (!$anomalies->contains('type', 'spend_without_conversions') || $negativeCount < 1) {
            return null;
        }

        return $this->decision(
            YandexDirectAiDecision::TYPE_NEGATIVE_KEYWORDS,
            70,
            YandexDirectAiDecision::RISK_LOW,
            [
                'suggested_action' => 'add_negative_keywords_from_waste_queries',
                'expected' => 'Снизить нерелевантные клики без остановки всей кампании.',
            ],
            [
                'why' => 'Есть расход без конверсий; нужно расширять минус-слова перед масштабированием.',
                'rules' => ['spend_without_conversions', 'has_negative_keyword_seed'],
            ],
            $signals,
        );
    }

    private function decision(string $type, int $confidence, string $risk, array $impact, array $reason, array $signals): array
    {
        return [
            'type' => $type,
            'confidence_score' => max(0, min(100, $confidence)),
            'risk_level' => $risk,
            'expected_impact' => $impact,
            'reason' => $reason,
            'signals' => $signals,
        ];
    }
}
