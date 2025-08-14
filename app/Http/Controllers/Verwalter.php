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
        $data = [
            'title' => 'Verwalter',
        ];

        return Inertia::render('Ameise/Verwalter', $data);
    }
}
