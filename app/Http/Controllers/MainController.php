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
        $categoriesQuery = Category::query()
            ->where('is_published', true)
            ->withCount(['products', 'goods'])
            ->when(
                Schema::hasColumn('categories', 'is_featured'),
                fn ($query) => $query->orderByDesc('is_featured')
            )
            ->when(
                Schema::hasColumn('categories', 'sort_order'),
                fn ($query) => $query->orderBy('sort_order')
            )
            ->orderBy('name');

        $categories = $categoriesQuery
            ->limit(12)
            ->get();

        $goodOfTheDay = $this->resolveGoodOfTheDay();

        $fields = $this->homeFields();

        $heroGoodsQuery = Good::query()
            ->where('is_published', true)
            ->whereNotNull(Schema::hasColumn('goods', 'ava_thumb') ? 'ava_thumb' : 'ava_image')
            ->inRandomOrder()
            ->limit(random_int(8, 16));

        $heroGoods = $heroGoodsQuery->get($this->goodImageColumns([
            'id',
            'name',
            'slug',
        ]));

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
            'description',
        ];

        $columns = $this->goodImageColumns($columns);

        if ($hasGoodCountry) {
            $relations[] = 'country:id,name,flag';
            $columns[] = 'country_id';
        }

        if ($this->hasPublicFieldCollections()) {
            $relations[] = 'fields:id,title,description';
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
                $fields = $good->relationLoaded('fields') ? $good->fields : collect();

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
                    'fields' => $fields
                        ->take(3)
                        ->map(fn ($field): array => [
                            'id' => $field->id,
                            'name' => $field->name,
                            'title' => $field->title,
                            'description' => $field->description,
                        ])
                        ->values(),
                ];
            })
            ->all();
    }

    private function homeFields(): array
    {
        if (! $this->hasPublicFieldCollections()) {
            return [];
        }

        return Field::query()
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
            ])
            ->all();
    }

    private function hasPublicFieldCollections(): bool
    {
        return Schema::hasTable('fields')
            && Schema::hasTable('field_good')
            && Schema::hasColumn('fields', 'slug')
            && Schema::hasColumn('fields', 'description')
            && Schema::hasColumn('fields', 'is_published')
            && Schema::hasColumn('fields', 'sort_order');
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
                'good:' . implode(',', $this->goodImageColumns(['id', 'name', 'slug'])),
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
            ->get($this->goodImageColumns([
                'id',
                'country_id',
                'name',
                'slug',
            ]))
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

    private function goodImageColumns(array $columns): array
    {
        $columns[] = 'ava_image';

        if (Schema::hasColumn('goods', 'ava_thumb')) {
            $columns[] = 'ava_thumb';
        }

        return array_values(array_unique($columns));
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
