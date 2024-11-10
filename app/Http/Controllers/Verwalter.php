<?php

namespace App\Http\Controllers;

use App\Models\Good;
use App\Models\Uri;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Verwalter extends Controller
{
    public function index()
    {
        $goods = Good::all();
        $uris = Uri::all();

        $data = [
            'title' => 'Verwalter',
            'goods' => $goods,
            'uris' => $uris,
        ];

        return Inertia::render('Verwalter', $data);
    }
}
