<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use Inertia\Inertia;

class CommodityController extends Controller
{
    public function show(Commodity $commodity)
    {
        return Inertia::render('Ameise/Commodity', [
            'commodityId' => $commodity->id,
        ]);
    }
}
