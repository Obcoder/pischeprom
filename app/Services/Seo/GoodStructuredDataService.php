<?php

namespace App\Services\Seo;

use App\Models\Good;

class GoodStructuredDataService
{
    public function __construct(
        private readonly GoodSeoService $seo,
    ) {}

    public function make(Good $good, bool $forceGenerate = false): array
    {
        if (
            !$forceGenerate
            && is_array($good->seo?->structured_data)
            && count($good->seo->structured_data)
        ) {
            return $this->normalizeStructuredData($good->seo->structured_data);
        }

        return [
            $this->product($good),
            $this->breadcrumbs($good),
        ];
    }

    private function normalizeStructuredData(array $structuredData): array
    {
        if (isset($structuredData['@type'])) {
            return [$structuredData];
        }

        return $structuredData;
    }

    public function product(Good $good): array
    {
        $price = $this->seo->price($good);
        $currency = $this->seo->currency($good);
        $image = $this->seo->image($good);
        $category = $this->seo->categoryTitle($good);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->seo->h1($good),
            'description' => $this->seo->description($good),
            'sku' => (string) $good->id,
            'url' => $this->seo->canonical($good),
        ];

        if ($image) {
            $data['image'] = [$image];
        }

        if ($category) {
            $data['category'] = $category;
        }

        $data['additionalProperty'] = [];

        if ($good->denominator) {
            $data['additionalProperty'][] = [
                '@type' => 'PropertyValue',
                'name' => 'Фасовка',
                'value' => "{$good->denominator} кг",
            ];
        }

        if ($good->vatRate) {
            $data['additionalProperty'][] = [
                '@type' => 'PropertyValue',
                'name' => 'НДС',
                'value' => "{$good->vatRate->title} / {$good->vatRate->rate}%",
            ];
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $this->seo->canonical($good),
            'priceCurrency' => $currency,
            'availability' => $this->seo->schemaAvailability($good),
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        if ($price) {
            $offer['price'] = number_format($price, 2, '.', '');
        } else {
            $offer['description'] = 'Цена по запросу';
        }

        $data['offers'] = $offer;

        return $data;
    }

    public function breadcrumbs(Good $good): array
    {
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Главная',
                'item' => route('home'),
            ],
        ];

        $category = $this->seo->category($good);

        if ($category) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $category->name,
                'item' => route('category.show', [
                    'category' => $category->slug ?: $category->id,
                ]),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'name' => $good->seo?->breadcrumbs_title ?: $good->name,
            'item' => $this->seo->canonical($good),
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
