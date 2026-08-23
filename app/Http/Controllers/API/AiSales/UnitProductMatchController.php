<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ReviewUnitProductMatchRequest;
use App\Http\Resources\AiSales\UnitProductMatchResource;
use App\Models\UnitProductMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitProductMatchController extends Controller
{
    public function review(
        ReviewUnitProductMatchRequest $request,
        UnitProductMatch $unitProductMatch,
        UnitProductMatchService $service,
        ProspectingFeatureGuard $features,
    ): JsonResponse {
        $features->dossier();
        Gate::authorize('review', $unitProductMatch);
        $match = $service->review($unitProductMatch, UnitProductMatchStatus::from($request->validated('status')), $request->user());

        return response()->json(['data' => (new UnitProductMatchResource($match))->resolve($request)]);
    }
}
