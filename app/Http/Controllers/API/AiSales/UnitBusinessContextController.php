<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\UnitBusinessContextService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\StoreUnitBusinessContextRequest;
use App\Http\Requests\AiSales\UpdateUnitBusinessContextRequest;
use App\Http\Resources\AiSales\UnitBusinessContextResource;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitBusinessContextController extends Controller
{
    public function store(
        StoreUnitBusinessContextRequest $request,
        Unit $unit,
        UnitBusinessContextService $contexts,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('manageContexts', $unit);
        $authorization->authorizeLane($request->user(), BusinessLane::from($request->validated('lane')));
        $context = $contexts->upsert($unit, $request->validated(), $request->user());

        return response()->json([
            'data' => (new UnitBusinessContextResource($context))->resolve($request),
        ], $context->wasRecentlyCreated ? 201 : 200);
    }

    public function update(
        UpdateUnitBusinessContextRequest $request,
        Unit $unit,
        UnitBusinessContext $context,
        UnitBusinessContextService $contexts,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        $authorization->assertContextBelongsToUnit($unit, $context);
        Gate::authorize('update', $context);
        $attributes = [
            ...$context->only([
                'confidence', 'owner_user_id', 'reviewer_user_id', 'primary_good_id',
                'primary_segment', 'source', 'first_activity_at', 'last_activity_at',
            ]),
            'lane' => $context->lane->value,
            'role_code' => $context->role_code->value,
            'stage' => $context->stage->value,
            'status' => $context->status->value,
            ...$request->validated(),
        ];
        $authorization->authorizeLane($request->user(), BusinessLane::from($attributes['lane']));
        $updated = $contexts->upsert($unit, $attributes, $request->user());

        return response()->json([
            'data' => (new UnitBusinessContextResource($updated))->resolve($request),
        ]);
    }
}
