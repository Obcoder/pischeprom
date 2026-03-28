<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class PurchaseController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function create()
    {
        return view('app');
    }

    public function edit(int $purchase)
    {
        return view('app');
    }
}
