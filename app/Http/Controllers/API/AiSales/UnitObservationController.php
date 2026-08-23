<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Domain\AiSales\Services\UnitObservationPromotionService;
use App\Domain\AiSales\Services\UnitObservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ReviewUnitObservationRequest;
use App\Http\Requests\AiSales\StoreUnitObservationRequest;
use App\Http\Resources\AiSales\UnitObservationResource;
use App\Models\Unit;
use App\Models\UnitObservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitObservationController extends Controller
{
    public function store(
        StoreUnitObservationRequest $request,
        Unit $unit,
        UnitObservationService $observations,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('manageObservations', $unit);
        $contextId = $request->validated('unit_business_context_id');

        if ($contextId) {
            $context = $unit->businessContexts()->findOrFail((int) $contextId);
            $authorization->authorizeLane($request->user(), $context->lane);
        }

        $observation = $observations->create($unit, $request->validated(), $request->user());

        return response()->json([
            'data' => (new UnitObservationResource($observation))->resolve($request),
        ], 201);
    }

    public function review(
        ReviewUnitObservationRequest $request,
        Unit $unit,
        UnitObservation $observation,
        UnitObservationService $observations,
    ): JsonResponse {
        abort_unless((int) $observation->unit_id === (int) $unit->id, 404);
        Gate::authorize('verify', $observation);
        $reviewed = $observations->review(
            $unit,
            $observation,
            ObservationVerificationStatus::from($request->validated('verification_status')),
            $request->user(),
        );

        return response()->json([
            'data' => (new UnitObservationResource($reviewed))->resolve($request),
        ]);
    }

    public function promote(
        Unit $unit,
        UnitObservation $observation,
        UnitObservationPromotionService $promotion,
    ): JsonResponse {
        abort_unless((int) $observation->unit_id === (int) $unit->id, 404);
        Gate::authorize('promote', $observation);
        $updated = $promotion->promote($unit, $observation, request()->user());

        return response()->json([
            'data' => ['id' => $updated->id, 'name' => $updated->name],
        ]);
    }
}
