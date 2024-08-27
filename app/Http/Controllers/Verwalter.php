<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class Verwalter extends Controller
{
    public function index()
    {
        return Inertia::render('Verwalter', ['title' => 'Verwalter']);
    }
}
