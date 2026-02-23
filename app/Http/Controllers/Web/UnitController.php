<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function show(Unit $unit)
    {
        $unit->load([
                        'entities.telephones',
                        'entities.sales',
                        'buildings.city',
                        'consumptions.product',
                        'consumptions.measure',
                        'manufactures',
                        'emails.sendings',
                        'telephones',
                        'uris',
                        'labels',
                        'stages',
                        'quotations.good',
                        'quotations.measure',
                    ]);

        return Inertia::render('Ameise/Unit', [
            'unit' => $unit,
        ]);
    }
}
