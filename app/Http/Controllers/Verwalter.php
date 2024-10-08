<?php

namespace App\Http\Controllers;

use App\Models\Good;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Verwalter extends Controller
{
    public function index()
    {
        $goods = Good::all();

        $data = [
            'title' => 'Verwalter',
            'goods' => $goods,
        ];

        return Inertia::render('Verwalter', $data);
    }
}
