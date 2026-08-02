<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsTrip;
use App\Services\Logistics\Map\MapConfigurationService;
use Illuminate\Http\JsonResponse;

class MapConfigurationController extends Controller
{
    public function __construct(private readonly MapConfigurationService $maps) {}

    public function show(): JsonResponse
    {
        $this->authorizeLogistics('viewAny', LogisticsTrip::class);

        return response()->json(['data' => $this->maps->configuration()]);
    }

    public function style(): JsonResponse
    {
        $this->authorizeLogistics('viewAny', LogisticsTrip::class);

        return response()->json($this->maps->style())
            ->header('Cache-Control', 'private, max-age=3600, must-revalidate');
    }
}
