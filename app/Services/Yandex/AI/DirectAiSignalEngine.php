<?php

namespace App\Services\Yandex\AI;

use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectCampaign;
use App\Services\Yandex\DirectIntelligentKeywordBuilderService;
use Illuminate\Support\Collection;

class DirectAiSignalEngine
{
    public function __construct(
        private readonly DirectIntelligentKeywordBuilderService $keywordBuilder,
        private readonly DirectAiPerformanceAnalyzer $performanceAnalyzer,
    ) {}

    public function collectSignals(Good $good): array
    {
        $good->loadMissing(['seo', 'products.category', 'yandexDirectAds', 'yandexDirectKeywords', 'yandexDirectDailyStats']);

        $keywordPlan = $this->keywordBuilder->build($good);
        $stats = $this->performanceAnalyzer->summary($good, (int) config('yandex.direct.ai_autopilot.learning_window_days', 14));
        $activeCampaigns = $this->activeCampaigns($good);
        $hasImage = filled($good->seo?->og_image ?: $good->ava_image ?: $good->ava_thumb);

        $signals = [
            'good' => [
                'id' => $good->id,
                'name' => $good->name,
                'slug' => $good->slug,
                'is_published' => (bool) $good->is_published,
                'has_image' => $hasImage,
                'category' => $good->products->first()?->category?->name,
            ],
            'seo' => [
                'score' => $this->seoScore($good, $good->seo, $keywordPlan),
                'has_h1' => filled($good->seo?->h1),
                'has_meta_title' => filled($good->seo?->meta_title),
                'has_meta_description' => filled($good->seo?->meta_description),
                'has_direct_title' => filled($good->seo?->yandex_direct_title_1),
                'has_direct_text' => filled($good->seo?->yandex_direct_text),
                'has_semantic_core' => $this->keywordBuilder->hasSemanticCore($good->seo),
                'has_search_queries' => filled($good->seo?->search_queries),
            ],
            'direct' => [
                'readiness_score' => $this->directReadinessScore($good, $good->seo, $keywordPlan, $hasImage),
                'has_ads' => $good->yandexDirectAds->isNotEmpty(),
                'latest_ad_status' => $good->yandexDirectAds->sortByDesc('id')->first()?->status,
                'has_external_campaign' => $activeCampaigns->isNotEmpty(),
                'active_campaigns' => $activeCampaigns->map(fn (YandexDirectCampaign $campaign) => [
                    'id' => $campaign->id,
                    'external_campaign_id' => $campaign->external_campaign_id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'daily_budget' => (float) $campaign->daily_budget,
                ])->values()->all(),
                'keyword_count' => count($keywordPlan['positive'] ?? []),
                'negative_keyword_count' => count($keywordPlan['negative'] ?? []),
            ],
            'stats' => $stats,
            'landing_behavior' => $this->landingBehavior($good),
            'search_queries' => [
                'source_count' => collect($keywordPlan['sources'] ?? [])->flatten()->filter()->unique()->count(),
                'positive_count' => count($keywordPlan['positive'] ?? []),
                'negative_count' => count($keywordPlan['negative'] ?? []),
                'sample' => collect($keywordPlan['positive'] ?? [])->pluck('phrase')->take(12)->values()->all(),
            ],
        ];

        $signals = $this->normalizeSignals($signals);
        $signals['ranked'] = $this->rankSignals($signals);
        $signals['anomalies'] = $this->detectAnomalies($signals);

        return $signals;
    }

    public function normalizeSignals(array $signals): array
    {
        data_set($signals, 'seo.score', $this->clamp((int) data_get($signals, 'seo.score')));
        data_set($signals, 'direct.readiness_score', $this->clamp((int) data_get($signals, 'direct.readiness_score')));

        foreach (['stats.ctr', 'stats.avg_cpc', 'stats.cpl', 'landing_behavior.bounce_rate'] as $key) {
            $value = data_get($signals, $key);
            data_set($signals, $key, is_numeric($value) ? round((float) $value, 2) : null);
        }

        return $signals;
    }

    public function rankSignals(array $signals): array
    {
        $ranked = [];

        foreach ([
            'SEO' => data_get($signals, 'seo.score'),
            'Direct readiness' => data_get($signals, 'direct.readiness_score'),
            'Clicks' => data_get($signals, 'stats.clicks'),
            'CTR' => data_get($signals, 'stats.ctr'),
            'Conversions' => data_get($signals, 'stats.conversions'),
            'Cost' => data_get($signals, 'stats.cost'),
            'Keyword coverage' => data_get($signals, 'direct.keyword_count'),
            'Bounce rate' => data_get($signals, 'landing_behavior.bounce_rate'),
        ] as $label => $value) {
            if ($value !== null) {
                $ranked[] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        usort($ranked, fn (array $a, array $b) => abs((float) $b['value']) <=> abs((float) $a['value']));

        return array_slice($ranked, 0, 8);
    }

    public function detectAnomalies(array $signals): array
    {
        $anomalies = [];
        $clicks = (int) data_get($signals, 'stats.clicks', 0);
        $cost = (float) data_get($signals, 'stats.cost', 0);
        $conversions = (int) data_get($signals, 'stats.conversions', 0);
        $ctr = data_get($signals, 'stats.ctr');
        $bounce = data_get($signals, 'landing_behavior.bounce_rate');

        if ($clicks >= (int) config('yandex.direct.ai_autopilot.min_clicks_threshold', 30) && $conversions === 0 && $cost > 0) {
            $anomalies[] = [
                'type' => 'spend_without_conversions',
                'severity' => 'high',
                'message' => 'Есть клики и расход, но нет конверсий.',
            ];
        }

        if ($ctr !== null && $ctr < (float) config('yandex.direct.ai_autopilot.low_ctr_threshold', 0.6) && (int) data_get($signals, 'stats.impressions', 0) > 500) {
            $anomalies[] = [
                'type' => 'low_ctr',
                'severity' => 'medium',
                'message' => 'Низкий CTR при достаточном числе показов.',
            ];
        }

        if ($bounce !== null && $bounce >= (float) config('yandex.direct.ai_autopilot.high_bounce_threshold', 75)) {
            $anomalies[] = [
                'type' => 'high_bounce',
                'severity' => 'medium',
                'message' => 'Высокий показатель отказов на посадочной странице.',
            ];
        }

        return $anomalies;
    }

    private function seoScore(Good $good, ?GoodSeo $seo, array $keywordPlan): int
    {
        $score = 0;
        $score += $good->is_published ? 10 : 0;
        $score += filled($good->name) ? 5 : 0;
        $score += filled($good->slug) ? 5 : 0;
        $score += filled($seo?->h1) ? 10 : 0;
        $score += filled($seo?->meta_title) ? 10 : 0;
        $score += filled($seo?->meta_description) ? 10 : 0;
        $score += filled($seo?->short_seo_text ?: $seo?->seo_text) ? 10 : 0;
        $score += filled($seo?->yandex_direct_title_1) ? 10 : 0;
        $score += filled($seo?->yandex_direct_text) ? 10 : 0;
        $score += $this->keywordBuilder->hasSemanticCore($seo) ? 10 : 0;
        $score += count($keywordPlan['positive'] ?? []) >= 5 ? 10 : 0;

        return $this->clamp($score);
    }

    private function directReadinessScore(Good $good, ?GoodSeo $seo, array $keywordPlan, bool $hasImage): int
    {
        $score = 0;
        $score += filled($seo?->yandex_direct_title_1 ?: $good->name) ? 15 : 0;
        $score += filled($seo?->yandex_direct_text ?: $seo?->short_seo_text) ? 20 : 0;
        $score += filled($seo?->yandex_direct_title_2) ? 10 : 0;
        $score += $hasImage ? 15 : 0;
        $score += count($keywordPlan['positive'] ?? []) >= 5 ? 25 : 0;
        $score += count($keywordPlan['negative'] ?? []) >= 5 ? 5 : 0;
        $score += $this->keywordBuilder->hasSemanticCore($seo) ? 10 : 0;

        return $this->clamp($score);
    }

    private function activeCampaigns(Good $good): Collection
    {
        return YandexDirectCampaign::query()
            ->where('good_id', $good->id)
            ->whereNotIn('status', [YandexDirectAd::STATUS_ERROR, YandexDirectAd::STATUS_SUSPENDED])
            ->where(function ($query) {
                $query->whereNotNull('external_campaign_id')
                    ->orWhereIn('status', [
                        YandexDirectAd::STATUS_SYNCING,
                        YandexDirectAd::STATUS_SENT,
                        YandexDirectAd::STATUS_MODERATION,
                        YandexDirectAd::STATUS_ACTIVE,
                    ]);
            })
            ->latest()
            ->get();
    }

    private function landingBehavior(Good $good): array
    {
        $rows = $good->yandexDirectDailyStats;
        $bounceValues = $rows
            ->map(fn ($row) => data_get($row->raw, 'bounce_rate') ?? data_get($row->raw, 'BounceRate'))
            ->filter(fn ($value) => is_numeric($value));

        return [
            'source' => $bounceValues->isNotEmpty() ? 'metrica_raw' : 'not_available',
            'bounce_rate' => $bounceValues->isNotEmpty() ? round((float) $bounceValues->avg(), 2) : null,
        ];
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
