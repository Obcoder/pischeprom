<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Field;
use App\Models\Good;
use App\Models\GoodOfTheDay;
use App\Models\Product;
use App\Services\Goods\HomeGoodsModuleService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
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
        ]);
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
