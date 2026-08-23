<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Services\EntityCandidateProposalService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\StoreEntityCandidateProposalRequest;
use App\Http\Resources\AiSales\EntityCandidateProposalResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EntityCandidateProposalController extends Controller
{
    public function store(
        StoreEntityCandidateProposalRequest $request,
        Unit $unit,
        EntityCandidateProposalService $proposals,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('proposeEntity', $unit);
        $context = $unit->businessContexts()->findOrFail((int) $request->validated('unit_business_context_id'));
        $authorization->authorizeLane($request->user(), $context->lane);
        Gate::authorize('proposeEntity', $context);
        $proposal = $proposals->propose($unit, $context, $request->validated(), $request->user());

        return response()->json([
            'data' => (new EntityCandidateProposalResource($proposal))->resolve($request),
        ], 201);
    }
}
