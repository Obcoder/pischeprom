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
            'featuredGoods' => $this->featuredGoods(),
            'homeBanners' => $this->homeBanners(),
        ]);
    }

    private function featuredGoods(): array
    {
        $hasGoodCountry = Schema::hasColumn('goods', 'country_id');
        $relations = [
            'products.category:id,name,slug',
            'fields:id,name,description',
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
            ->where('is_published', true)
            ->with($relations)
            ->inRandomOrder()
            ->limit(8)
            ->get($columns)
            ->values()
            ->map(function (Good $good): array {
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
                    'fields' => $good->fields
                        ->take(3)
                        ->map(fn ($field): array => [
                            'id' => $field->id,
                            'name' => $field->name,
                            'description' => $field->description,
                        ])
                        ->values(),
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
