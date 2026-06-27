<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Field;
use App\Models\Good;
use App\Models\GoodOfTheDay;
use App\Models\HomeBanner;
use App\Models\Product;
use App\Services\Goods\HomeGoodsModuleService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MainController extends Controller
{
    public function index(Request $request, HomeGoodsModuleService $homeGoodsModuleService): Response
    {
        $categories = Category::query()
            ->where('is_published', true)
            ->withCount(['products', 'goods'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $goodOfTheDay = $this->resolveGoodOfTheDay();

        $fields = Field::query()
            ->published()
            ->withCount([
                'goods as goods_count' => fn ($query) => $query->where('goods.is_published', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(12)
            ->get([
                'id',
                'title',
                'slug',
                'description',
                'sort_order',
                'is_published',
            ]);

        $heroGoods = Good::query()
            ->where('is_published', true)
            ->whereNotNull('ava_thumb')
            ->inRandomOrder()
            ->limit(random_int(8, 16))
            ->get([
                      'id',
                      'name',
                      'slug',
                      'ava_image',
                      'ava_thumb',
                  ]);

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'fields' => $fields,
            'goodOfTheDay' => $goodOfTheDay,
            'productsCount' => Product::query()->count(),
            'goodsCount' => Good::query()->count(),
            'heroGoods' => $heroGoods,
            'homeGoodsModule' => $homeGoodsModuleService->build($request->user()),
            'countryCollections' => $this->countryCollections(),
            'topSalesGoods' => $this->topSalesGoods(),
            'homeBanners' => $this->homeBanners(),
        ]);
    }

    private function topSalesGoods(): array
    {
        if (! Schema::hasTable('good_sale') || ! Schema::hasTable('sales')) {
            return [];
        }

        $rankedRows = DB::table('good_sale')
            ->join('sales', 'sales.id', '=', 'good_sale.sale_id')
            ->join('goods', 'goods.id', '=', 'good_sale.good_id')
            ->where('goods.is_published', true)
            ->select('good_sale.good_id')
            ->selectRaw('COUNT(DISTINCT sales.id) as sales_count')
            ->selectRaw('SUM(good_sale.quantity) as quantity')
            ->selectRaw('SUM(good_sale.total) as total')
            ->selectRaw('AVG(good_sale.price) as average_price')
            ->selectRaw('MAX(sales.date) as last_sale_date')
            ->groupBy('good_sale.good_id')
            ->orderByDesc('total')
            ->orderByDesc('sales_count')
            ->limit(8)
            ->get();

        if ($rankedRows->isEmpty()) {
            return [];
        }

        $statsByGoodId = $rankedRows->keyBy('good_id');
        $rankByGoodId = $rankedRows
            ->pluck('good_id')
            ->flip()
            ->map(fn ($index) => $index + 1);
        $hasGoodCountry = Schema::hasColumn('goods', 'country_id');
        $relations = [
            'products.category:id,name,slug',
            'publishedMedia' => function ($query): void {
                $query
                    ->where('type', 'image')
                    ->where('is_published', true)
                    ->orderByDesc('is_ava')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ];
        $columns = [
            'id',
            'name',
            'slug',
            'ava_image',
            'ava_thumb',
            'description',
        ];

        if ($hasGoodCountry) {
            $relations[] = 'country:id,name,flag';
            $columns[] = 'country_id';
        }

        return Good::query()
            ->whereIn('id', $rankedRows->pluck('good_id'))
            ->with($relations)
            ->get($columns)
            ->sortBy(fn (Good $good) => $rankByGoodId[$good->id] ?? 999)
            ->values()
            ->map(function (Good $good) use ($statsByGoodId, $rankByGoodId): array {
                $stats = $statsByGoodId[$good->id];
                $mediaImage = $good->publishedMedia->first();

                return [
                    'id' => $good->id,
                    'name' => $good->name,
                    'slug' => $good->slug,
                    'description' => $good->description,
                    'ava_image' => $good->ava_image,
                    'ava_thumb' => $good->ava_thumb,
                    'image' => $mediaImage?->url ?: $good->ava_image ?: $good->ava_thumb,
                    'country' => $good->relationLoaded('country') ? $good->country : null,
                    'products' => $good->products->take(3)->values(),
                    'rank' => $rankByGoodId[$good->id] ?? null,
                    'sales_count' => (int) $stats->sales_count,
                    'quantity' => (float) $stats->quantity,
                    'total' => (float) $stats->total,
                    'average_price' => (float) $stats->average_price,
                    'last_sale_date' => $stats->last_sale_date,
                ];
            })
            ->all();
    }

    private function homeBanners(): array
    {
        if (! Schema::hasTable('home_banners')) {
            return [];
        }

        return HomeBanner::query()
            ->published()
            ->active()
            ->with([
                'good:id,name,slug,ava_thumb,ava_image',
                'product:id,rus,eng,category_id',
                'product.category:id,name,slug',
                'category:id,name,slug',
            ])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->all();
    }

    private function countryCollections(): array
    {
        if (! Schema::hasColumn('goods', 'country_id')) {
            return [];
        }

        return Good::query()
            ->where('is_published', true)
            ->whereNotNull('country_id')
            ->with('country:id,name,flag')
            ->orderBy('name')
            ->get([
                'id',
                'country_id',
                'name',
                'slug',
                'ava_image',
                'ava_thumb',
            ])
            ->groupBy('country_id')
            ->map(function ($goods) {
                $country = $goods->first()?->country;

                if (! $country?->id) {
                    return null;
                }

                return [
                    'country' => $country,
                    'goods_count' => $goods->count(),
                    'goods' => $goods->take(3)->values(),
                ];
            })
            ->filter()
            ->sortByDesc('goods_count')
            ->values()
            ->take(8)
            ->all();
    }

    private function resolveGoodOfTheDay(): ?GoodOfTheDay
    {
        $today = Carbon::today()->toDateString();

        $record = GoodOfTheDay::query()
            ->with('good')
            ->firstWhere('date', $today);

        if ($record) {
            return $record;
        }

        $randomGood = Good::query()
            ->where('is_published', true)
            ->inRandomOrder()
            ->first();

        if (! $randomGood) {
            return null;
        }

        return GoodOfTheDay::query()
            ->create([
                         'good_id' => $randomGood->id,
                         'date' => $today,
                     ])
            ->load('good');
    }
}
