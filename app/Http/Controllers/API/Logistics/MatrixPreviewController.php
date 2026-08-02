<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\MatrixPreviewRequest;
use App\Models\LogisticsCityDistance;
use App\Services\Logistics\Map\MatrixRoutePreviewService;
use Illuminate\Http\JsonResponse;

class MatrixPreviewController extends Controller
{
    public function __construct(private readonly MatrixRoutePreviewService $previews) {}

    public function __invoke(
        MatrixPreviewRequest $request,
        LogisticsCityDistance $distance,
    ): JsonResponse {
        return response()->json(['data' => $this->previews->preview($distance)]);
    }
}
