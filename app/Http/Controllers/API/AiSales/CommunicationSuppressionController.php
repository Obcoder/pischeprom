<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Outreach\CommunicationSuppressionService;
use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ClearCommunicationSuppressionRequest;
use App\Http\Requests\AiSales\StoreCommunicationSuppressionRequest;
use App\Models\CommunicationSuppression;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CommunicationSuppressionController extends Controller
{
    public function store(
        StoreCommunicationSuppressionRequest $request,
        Unit $unit,
        CommunicationSuppressionService $service,
        OutreachAuthorizationService $authorization,
    ): JsonResponse {
        $context = UnitBusinessContext::query()->where('unit_id', $unit->id)->findOrFail($request->integer('unit_business_context_id'));
        $authorization->authorize($request->user(), OutreachAuthorizationService::MANAGE_SUPPRESSIONS, $unit, $context);
        $suppression = $service->create($request->user(), $unit, $context, $request->validated());

        return response()->json(['data' => [
            'id' => $suppression->id, 'public_id' => $suppression->public_id,
            'scope' => $suppression->scope->value, 'reason' => $suppression->reason->value,
            'active_from' => $suppression->active_from->toISOString(),
        ]], 201);
    }

    public function clear(
        ClearCommunicationSuppressionRequest $request,
        Unit $unit,
        CommunicationSuppression $communicationSuppression,
        CommunicationSuppressionService $service,
    ): JsonResponse {
        abort_unless((int) $communicationSuppression->unit_id === (int) $unit->id, 404);
        Gate::authorize('update', $communicationSuppression);
        $suppression = $service->clear(
            $communicationSuppression, $request->user(), $request->validated('reason_code'), $request->validated('safe_note'),
        );

        return response()->json(['data' => [
            'id' => $suppression->id, 'cleared_at' => $suppression->cleared_at->toISOString(),
            'clear_reason_code' => $suppression->clear_reason_code,
        ]]);
    }
}
