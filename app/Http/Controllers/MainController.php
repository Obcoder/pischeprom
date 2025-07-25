<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Good;
use App\Models\GoodOfTheDay;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MainController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_published', 1)
            ->inRandomOrder()
            ->limit(12)
            ->get();
        $good = Good::inRandomOrder()->first();
        $goodOfTheDay = GoodOfTheDay::create([
            'good_id' => $good->id,
            'date' => date('Y-m-d'),
                                                 ]);
        $productsCount = Product::count();
        $goodsCount = Good::count();

        $data = [
            'categories' => $categories,
            'goodOfTheDay' => $goodOfTheDay::with('good')->find($goodOfTheDay->id),
            'productsCount' => $productsCount,
            'goodsCount' => $goodsCount,
        ];

        return Inertia::render('Welcome', $data);
    }
}
