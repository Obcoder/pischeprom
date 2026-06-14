<?php

namespace App\Services\Yandex;

use App\Models\Good;
use Illuminate\Support\Str;

class DirectStructurePlannerService
{
    public function __construct(
        private readonly DirectCampaignNamingService $namingService,
        private readonly DirectIntelligentKeywordBuilderService $keywordBuilder,
        private readonly GoodDirectDraftService $draftService,
        private readonly DirectRegionResolverService $regionResolver,
    ) {}

    public function plan(Good $good): array
    {
        $good->loadMissing(['seo', 'products.category']);
        $seo = $good->seo;
        $keywords = $this->keywordBuilder->build($good);
        $regions = $this->regionIds();
        $campaignName = $this->namingService->campaign($good, 'FULL_AUTO', $regions);

        return [
            'campaign' => [
                'name' => $campaignName,
                'type' => $this->decideCampaignType($good),
                'strategy' => $this->decideStrategy($good),
                'daily_budget' => $this->assignBudget($good),
                'region_ids' => $regions,
                'minus_keywords' => $keywords['negative'],
            ],
            'ad_groups' => $this->buildAdGroups($good, $keywords),
            'keywords' => $keywords,
        ];
    }

    public function buildAdGroups(Good $good, ?array $keywordPlan = null): array
    {
        $keywordPlan ??= $this->keywordBuilder->build($good);
        $segments = $this->splitByIntent();

        return collect($segments)
            ->map(function (array $segment) use ($good, $keywordPlan) {
                return [
                    'segment' => $segment['key'],
                    'name' => $this->namingService->adGroup($good, $segment['label']),
                    'region_ids' => $this->regionIds(),
                    'minus_keywords' => $keywordPlan['negative'],
                    'ads' => $this->buildAdVariants($good, $segment['key']),
                    'keywords' => $this->distributeKeywords($keywordPlan['positive'], $segment['key']),
                ];
            })
            ->values()
            ->all();
    }

    public function splitByIntent(): array
    {
        return [
            ['key' => 'b2b', 'label' => 'B2B'],
            ['key' => 'retail', 'label' => 'Retail'],
            ['key' => 'mixed', 'label' => 'Mixed'],
        ];
    }

    public function buildAdVariants(Good $good, string $segment): array
    {
        $seo = $good->seo;
        $baseTitle = $this->limit($seo?->yandex_direct_title_1 ?: $good->name, (int) config('yandex.limits.title_1'));
        $href = $this->draftService->buildHrefWithUtm($good, $seo?->utm_template);
        $image = $seo?->og_image ?: $good->ava_image ?: $good->ava_thumb;

        $templates = [
            'commercial' => [
                'title_1' => $baseTitle,
                'title_2' => $seo?->yandex_direct_title_2 ?: 'Опт и розница',
                'text' => $seo?->yandex_direct_text ?: 'Поставки для пищевой промышленности. Быстрая обработка заявки.',
            ],
            'b2b' => [
                'title_1' => $baseTitle,
                'title_2' => 'Для HoReCa и производств',
                'text' => 'Оптовые поставки для бизнеса. Подберём формат и условия поставки.',
            ],
            'regional' => [
                'title_1' => $baseTitle,
                'title_2' => 'Поставки в СПб',
                'text' => 'Пищевое сырьё и продукты для компаний. Доставка и документы.',
            ],
        ];

        return collect($templates)
            ->map(function (array $template, string $variant) use ($good, $segment, $href, $image) {
                return [
                    'variant' => $variant,
                    'segment' => $segment,
                    'name' => $this->namingService->ad($good, $variant),
                    'title_1' => $this->limit($template['title_1'], (int) config('yandex.limits.title_1')),
                    'title_2' => $this->limit($template['title_2'], (int) config('yandex.limits.title_2')),
                    'text' => $this->limit($template['text'], (int) config('yandex.limits.text')),
                    'href' => $href,
                    'utm_template' => $good->seo?->utm_template ?: config('yandex.default_utm_template'),
                    'image_url' => $image,
                ];
            })
            ->values()
            ->all();
    }

    public function distributeKeywords(array $keywords, string $segment): array
    {
        $segmentHints = [
            'b2b' => ['опт', 'b2b', 'хорека', 'horeca', 'производство', 'для бизнеса', 'поставщик'],
            'retail' => ['купить', 'магазин', 'розница', 'цена'],
            'mixed' => [],
        ];

        if ($segment === 'mixed') {
            return collect($keywords)->take(80)->values()->all();
        }

        $hints = $segmentHints[$segment] ?? [];
        $filtered = collect($keywords)->filter(function (array $keyword) use ($hints) {
            foreach ($hints as $hint) {
                if (str_contains($keyword['phrase'], $hint)) {
                    return true;
                }
            }

            return false;
        });

        return ($filtered->isNotEmpty() ? $filtered : collect($keywords))
            ->take(60)
            ->values()
            ->all();
    }

    public function decideCampaignType(Good $good): string
    {
        return 'TEXT_CAMPAIGN';
    }

    public function decideStrategy(Good $good): string
    {
        return 'WB_MAXIMUM_CLICKS';
    }

    public function assignBudget(Good $good): float
    {
        return (float) config('yandex.direct.auto_launch.daily_budget', 300);
    }

    public function assignRegions(): array
    {
        return $this->regionIds();
    }

    private function regionIds(): array
    {
        return $this->regionResolver->regionIds();
    }

    private function limit(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?: '');

        return Str::limit($value, $limit, '');
    }
}
