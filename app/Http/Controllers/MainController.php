<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Good;
use App\Models\GoodOfTheDay;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MainController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $good = Good::inRandomOrder()->first();
        $goodOfTheDay = GoodOfTheDay::create([
            'good_id' => $good->id,
            'date' => date('Y-m-d'),
                                                 ]);

        $data = [
            'categories' => $categories,
            'goodOfTheDay' => $goodOfTheDay::with('good')->find($goodOfTheDay->id),
        ];

        return Inertia::render('Welcome', $data);
    }
}
