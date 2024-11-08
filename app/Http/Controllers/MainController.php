<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GoodOfTheDay;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MainController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $goodOfTheDay = GoodOfTheDay::with('good')
            ->inRandomOrder()->first();

        $data = [
            'categories' => $categories,
            'goodOfTheDay' => $goodOfTheDay,
        ];

        return Inertia::render('Welcome', $data);
    }
}
