<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Services\Yandex\GoodDirectDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class YandexDirectGoodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->input('category_id');
        $seoStatus = $request->input('seo_status');
        $directStatus = $request->input('direct_status');
        $published = $request->input('is_published');

        $query = Good::query()
            ->with([
                'seo',
                'products.category',
                'yandexDirectAds' => fn ($q) => $q->latest()->limit(1),
                'yandexDirectDailyStats',
            ])
            ->withCount([
                'yandexDirectKeywords as direct_keywords_count' => fn ($q) => $q->where('is_negative', false),
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('seo', fn ($seo) => $seo->where('h1', 'like', "%{$search}%"));
            });
        }

        if (filled($categoryId)) {
            $query->whereHas('products.category', fn ($q) => $q->where('categories.id', $categoryId));
        }

        if ($seoStatus === 'filled') {
            $query->whereHas('seo', fn ($q) => $q->whereNotNull('yandex_direct_title_1')->whereNotNull('yandex_direct_text'));
        } elseif ($seoStatus === 'empty') {
            $query->whereDoesntHave('seo', fn ($q) => $q->whereNotNull('yandex_direct_title_1')->whereNotNull('yandex_direct_text'));
        }

        if (filled($directStatus)) {
            $query->whereHas('yandexDirectAds', fn ($q) => $q->where('status', $directStatus));
        }

        if ($published !== null && $published !== '') {
            $query->where('is_published', filter_var($published, FILTER_VALIDATE_BOOLEAN));
        }

        $paginator = $query->orderBy('name')->paginate($perPage);

        $items = collect($paginator->items())->map(function (Good $good) {
            $stats = $good->yandexDirectDailyStats;
            $ad = $good->yandexDirectAds->first();

            return [
                'id' => $good->id,
                'name' => $good->name,
                'slug' => $good->slug,
                'ava_thumb' => $good->ava_thumb,
                'is_published' => $good->is_published,
                'category' => $good->products->first()?->category,
                'seo' => $good->seo,
                'direct_status' => $ad?->status,
                'direct_ad_id' => $ad?->id,
                'direct_keywords_count' => $good->direct_keywords_count,
                'stats' => [
                    'impressions' => (int) $stats->sum('impressions'),
                    'clicks' => (int) $stats->sum('clicks'),
                    'cost' => (float) $stats->sum('cost'),
                    'conversions' => (int) $stats->sum('conversions'),
                    'ctr' => $stats->sum('impressions') > 0 ? round($stats->sum('clicks') / $stats->sum('impressions') * 100, 2) : null,
                    'cost_per_conversion' => $stats->sum('conversions') > 0 ? round($stats->sum('cost') / $stats->sum('conversions'), 2) : null,
                ],
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function generateDraft(Good $good, GoodDirectDraftService $service): JsonResponse
    {
        try {
            $ad = $service->generateForGood($good);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($ad);
    }
}
