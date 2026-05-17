<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Services\Seo\GoodSeoService;
use App\Services\Seo\GoodStructuredDataService;
use Inertia\Inertia;
use Inertia\Response;

class GoodController extends Controller
{
    public function show(
        Good $good,
        GoodSeoService $seoService,
        GoodStructuredDataService $structuredDataService,
    ): Response {
        abort_unless($good->is_published, 404);

        $good->load([
                        'products.category',
                        'vatRate:id,title,rate',
                        'seo',
                        'latestPrice.currency',

                        'publishedMedia' => function ($query) {
                            $query
                                ->where('is_published', true)
                                ->orderByDesc('is_ava')
                                ->orderBy('sort_order')
                                ->orderBy('id');
                        },

                        'priceTypeValues' => function ($query) {
                            $query
                                ->where('is_published', true)
                                ->with([
                                           'priceType.currency',
                                           'currency',
                                       ])
                                ->orderByDesc('updated_at');
                        },
                    ]);

        $relatedGoods = Good::query()
            ->where('id', '!=', $good->id)
            ->where('is_published', true)
            ->with([
                       'seo',
                       'latestPrice.currency',
                       'publishedMedia' => function ($query) {
                           $query
                               ->where('type', 'image')
                               ->where('is_published', true)
                               ->orderByDesc('is_ava')
                               ->orderBy('sort_order')
                               ->orderBy('id');
                       },
                   ])
            ->inRandomOrder()
            ->limit(8)
            ->get([
                      'id',
                      'name',
                      'slug',
                      'ava_thumb',
                      'ava_image',
                      'description',
                  ]);

        return Inertia::render('Goods/Show', [
            'good' => $good,
            'relatedGoods' => $relatedGoods,

            'seo' => [
                'title' => $seoService->title($good),
                'description' => $seoService->description($good),
                'h1' => $seoService->h1($good),
                'canonical' => $seoService->canonical($good),
                'robots' => $seoService->robots($good),
                'image' => $seoService->image($good),
                'category' => $seoService->categoryTitle($good),
                'price' => $seoService->price($good),
                'currency' => $seoService->currency($good),
                'jsonLd' => $structuredDataService->make($good),
                'metricaCounterId' => config('services.yandex_metrica.counter_id'),
            ],
        ]);
    }
}
