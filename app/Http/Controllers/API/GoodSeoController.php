<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Services\Seo\GoodStructuredDataService;
use App\Services\Seo\IndexNowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodSeoController extends Controller
{
    public function show(Good $good): JsonResponse
    {
        $seo = $good->seo()->firstOrCreate([
                                               'good_id' => $good->id,
                                           ], [
                                               'meta_title' => "{$good->name} купить оптом для пищевой промышленности",
                                               'meta_description' => $good->description,
                                               'h1' => $good->name,
                                               'robots' => 'index,follow',
                                               'focus_keyword' => $good->name,
                                               'breadcrumbs_title' => $good->name,
                                               'is_active' => true,
                                               'include_in_sitemap' => true,
                                               'include_in_yandex_feed' => true,
                                               'availability_status' => 'on_request',
                                           ]);

        return response()->json($seo);
    }

    public function upsert(
        Request $request,
        Good $good,
        IndexNowService $indexNowService,
    ): JsonResponse {
        $validated = $request->validate([
                                            'meta_title' => ['nullable', 'string', 'max:255'],
                                            'meta_description' => ['nullable', 'string'],
                                            'h1' => ['nullable', 'string', 'max:255'],
                                            'slug_override' => ['nullable', 'string', 'max:255'],
                                            'canonical_url' => ['nullable', 'string', 'max:255'],
                                            'robots' => ['required', 'string', 'max:50'],

                                            'og_title' => ['nullable', 'string', 'max:255'],
                                            'og_description' => ['nullable', 'string'],
                                            'og_image' => ['nullable', 'string', 'max:255'],

                                            'twitter_title' => ['nullable', 'string', 'max:255'],
                                            'twitter_description' => ['nullable', 'string'],
                                            'twitter_image' => ['nullable', 'string', 'max:255'],

                                            'short_seo_text' => ['nullable', 'string'],
                                            'seo_text' => ['nullable', 'string'],

                                            'semantic_core' => ['nullable', 'array'],
                                            'keywords' => ['nullable', 'array'],
                                            'search_queries' => ['nullable', 'array'],
                                            'structured_data' => ['nullable', 'array'],

                                            'focus_keyword' => ['nullable', 'string', 'max:255'],
                                            'breadcrumbs_title' => ['nullable', 'string', 'max:255'],

                                            'is_active' => ['required', 'boolean'],
                                            'include_in_sitemap' => ['nullable', 'boolean'],
                                            'include_in_yandex_feed' => ['nullable', 'boolean'],

                                            'yandex_direct_title_1' => ['nullable', 'string', 'max:255'],
                                            'yandex_direct_title_2' => ['nullable', 'string', 'max:255'],
                                            'yandex_direct_text' => ['nullable', 'string'],
                                            'utm_template' => ['nullable', 'string'],

                                            'availability_status' => ['nullable', 'string', 'max:50'],
                                            'min_order' => ['nullable', 'string', 'max:255'],
                                            'delivery_note' => ['nullable', 'string'],
                                            'payment_note' => ['nullable', 'string'],
                                            'faq' => ['nullable', 'array'],
                                        ]);

        $seo = $good->seo()->updateOrCreate(
            ['good_id' => $good->id],
            [
                ...$validated,
                'include_in_sitemap' => $validated['include_in_sitemap'] ?? true,
                'include_in_yandex_feed' => $validated['include_in_yandex_feed'] ?? true,
                'availability_status' => $validated['availability_status'] ?? 'on_request',
            ]
        );

        if ($good->is_published && $seo->is_active && str_starts_with($seo->robots, 'index')) {
            if ($indexNowService->submit([route('public.goods.show', $good)])) {
                $seo->update([
                                 'index_now_sent_at' => now(),
                             ]);
            }
        }

        return response()->json($seo->fresh());
    }

    public function generateStructuredData(
        Good $good,
        GoodStructuredDataService $structuredDataService,
    ): JsonResponse {
        $good->load([
                        'seo',
                        'products.category',
                        'vatRate',
                        'latestPrice.currency',
                        'publishedMedia',
                        'priceTypeValues.priceType.currency',
                        'priceTypeValues.currency',
                    ]);

        $seo = $good->seo()->firstOrCreate([
                                               'good_id' => $good->id,
                                           ]);

        $seo->update([
                         'structured_data' => $structuredDataService->make($good),
                         'last_generated_at' => now(),
                     ]);

        return response()->json($seo->fresh());
    }
}
