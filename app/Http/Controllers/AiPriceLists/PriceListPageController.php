<?php

namespace App\Http\Controllers\AiPriceLists;

use App\Http\Controllers\Controller;
use App\Models\PriceListImport;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PriceListPageController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PriceListImport::class);

        return Inertia::render('Ameise/Ai/PriceLists/Index');
    }

    public function show(PriceListImport $priceListImport): Response
    {
        Gate::authorize('view', $priceListImport);

        return Inertia::render('Ameise/Ai/PriceLists/Show', [
            'importUuid' => $priceListImport->uuid,
        ]);
    }
}
