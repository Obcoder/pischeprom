<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\UnitGoodMatchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ReviewUnitGoodMatchRequest;
use App\Http\Resources\AiSales\UnitGoodMatchResource;
use App\Models\UnitGoodMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitGoodMatchController extends Controller
{
    public function review(
        ReviewUnitGoodMatchRequest $request,
        UnitGoodMatch $unitGoodMatch,
        UnitGoodMatchService $service,
        ProspectingFeatureGuard $features,
    ): JsonResponse {
        $features->dossier();
        Gate::authorize('review', $unitGoodMatch);
        $match = $service->review($unitGoodMatch, UnitGoodMatchStatus::from($request->validated('status')), $request->user());

        return response()->json(['data' => (new UnitGoodMatchResource($match))->resolve($request)]);
    }
}
