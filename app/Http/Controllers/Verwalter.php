<?php

namespace App\Http\Controllers;

use App\Models\Action;
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
        $actions = Action::all();

        $data = [
            'title' => 'Verwalter',
            'goods' => $goods,
            'uris' => $uris,
            'actions' => $actions,
        ];

        return Inertia::render('Verwalter', $data);
    }
}
