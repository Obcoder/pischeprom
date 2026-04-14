<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Good;
use App\Models\GoodOfTheDay;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MainController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->where('is_published', true)
            ->inRandomOrder()
            ->limit(12)
            ->get();

        $goodOfTheDay = $this->resolveGoodOfTheDay();

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
            'goodOfTheDay' => $goodOfTheDay,
            'productsCount' => Product::query()->count(),
            'goodsCount' => Good::query()->count(),
            'heroGoods' => $heroGoods,
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
