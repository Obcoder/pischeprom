<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Services\UnitAliasService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\StoreUnitAliasRequest;
use App\Http\Resources\AiSales\UnitAliasResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitAliasController extends Controller
{
    public function store(
        StoreUnitAliasRequest $request,
        Unit $unit,
        UnitAliasService $aliases,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('manageAliases', $unit);
        $contextId = $request->validated('unit_business_context_id');

        if ($contextId) {
            $context = $unit->businessContexts()->findOrFail((int) $contextId);
            $authorization->authorizeLane($request->user(), $context->lane);
        }

        $alias = $aliases->create($unit, $request->validated(), $request->user());

        return response()->json([
            'data' => (new UnitAliasResource($alias))->resolve($request),
        ], $alias->wasRecentlyCreated ? 201 : 200);
    }
}
