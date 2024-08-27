<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MainController extends Controller
{
    public function index()
    {
        $categories = Category::all();

        $data = [
            'categories' => $categories,
        ];

        return Inertia::render('Welcome', $data);
    }
}
