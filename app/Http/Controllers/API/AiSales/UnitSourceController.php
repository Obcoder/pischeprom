<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Domain\AiSales\Services\UnitSourceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\StoreUnitSourceRequest;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitSourceController extends Controller
{
    public function store(
        StoreUnitSourceRequest $request,
        Unit $unit,
        UnitSourceService $sources,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('manageObservations', $unit);
        $contextId = $request->validated('unit_business_context_id');

        if ($contextId) {
            $context = $unit->businessContexts()->findOrFail((int) $contextId);
            $authorization->authorizeLane($request->user(), $context->lane);
        }

        $source = $sources->create($unit, $request->validated(), $request->user());

        return response()->json([
            'data' => [
                'id' => $source->id,
                'unit_business_context_id' => $source->unit_business_context_id,
                'type' => $source->source_type,
                'label' => $source->source_label,
                'reference' => $source->source_reference,
                'url' => $source->source_url,
            ],
        ], $source->wasRecentlyCreated ? 201 : 200);
    }
}
