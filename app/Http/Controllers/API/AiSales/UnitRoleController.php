<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Domain\AiSales\Services\UnitMarketRoleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\StoreUnitRoleRequest;
use App\Models\MarketRole;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitRoleController extends Controller
{
    public function store(
        StoreUnitRoleRequest $request,
        Unit $unit,
        UnitMarketRoleService $roles,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('manageRoles', $unit);
        $code = UnitRoleCode::from($request->validated('role_code'));
        $authorization->authorizeLane($request->user(), $code->defaultLane());
        $assignment = $roles->assign($unit, $code, $request->user(), $request->validated('source') ?? 'manual');

        return response()->json([
            'data' => [
                'id' => $assignment->role->id,
                'code' => $assignment->role->code,
                'display_name' => $assignment->role->display_name,
            ],
        ], 201);
    }

    public function destroy(
        Unit $unit,
        MarketRole $marketRole,
        UnitMarketRoleService $roles,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('manageRoles', $unit);
        $code = UnitRoleCode::tryFrom($marketRole->code);
        abort_unless($code !== null, 404);
        $authorization->authorizeLane(request()->user(), $code->defaultLane());
        $roles->archive($unit, $marketRole, request()->user());

        return response()->json(null, 204);
    }
}
