<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Runs\CancelAiAgentRun;
use App\Domain\AiSales\Runs\CreateAiAgentRun;
use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\StoreAiAgentRunRequest;
use App\Http\Resources\AiSales\AiAgentRunResource;
use App\Jobs\AiSales\ExecuteAiAgentRunJob;
use App\Models\AiAgentDefinition;
use App\Models\AiAgentRun;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiAgentRunController extends Controller
{
    public function index(
        Request $request,
        AiControlPlaneAuthorizationService $authorization,
        UnitContextAuthorizationService $unitAuthorization,
    ): JsonResponse {
        abort_unless(
            $authorization->canView($request->user())
                && $unitAuthorization->hasPermission($request->user(), AiControlPlaneAuthorizationService::VIEW_RUNS),
            403,
        );

        $visibleLanes = collect(\App\Domain\AiSales\Enums\BusinessLane::cases())
            ->filter(fn ($lane) => $unitAuthorization->canViewLane($request->user(), $lane))
            ->pluck('value');
        $runs = AiAgentRun::query()
            ->whereIn('lane', $visibleLanes)
            ->whereHas('businessContext', fn ($query) => $query
                ->whereIn('lane', $visibleLanes)
                ->whereColumn('unit_business_contexts.lane', 'ai_agent_runs.lane'))
            ->when($request->integer('unit_id') > 0, fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
            ->with('steps')
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => AiAgentRunResource::collection($runs)->resolve($request)]);
    }

    public function store(
        StoreAiAgentRunRequest $request,
        CreateAiAgentRun $create,
        AiControlPlaneAuthorizationService $authorization,
    ): JsonResponse {
        $unit = Unit::query()
            ->without(['fields', 'labels', 'telephones', 'uris'])
            ->select(['id', 'name'])
            ->findOrFail((int) $request->validated('unit_id'));
        $context = UnitBusinessContext::query()->findOrFail((int) $request->validated('unit_business_context_id'));
        $definition = AiAgentDefinition::query()
            ->where('code', $request->validated('definition_code'))
            ->where('version', $request->validated('definition_version'))
            ->firstOrFail();

        Gate::authorize('view', $unit);
        Gate::authorize('view', $context);
        abort_unless($authorization->canRun($request->user(), $unit, $context), 403);

        $result = $create->handle(
            $request->user(),
            $unit,
            $context,
            $definition,
            $request->validated('idempotency_key'),
        );

        if ($result->created) {
            ExecuteAiAgentRunJob::dispatch($result->run->id)->afterCommit();
        }

        $run = $result->run->fresh()->load('steps');

        return response()->json([
            'data' => (new AiAgentRunResource($run))->resolve($request),
        ], $result->created ? 201 : 200);
    }

    public function show(Request $request, AiAgentRun $aiAgentRun): JsonResponse
    {
        Gate::authorize('view', $aiAgentRun);

        return response()->json([
            'data' => (new AiAgentRunResource($aiAgentRun->load('steps')))->resolve($request),
        ]);
    }

    public function cancel(
        Request $request,
        AiAgentRun $aiAgentRun,
        CancelAiAgentRun $cancel,
    ): JsonResponse {
        Gate::authorize('cancel', $aiAgentRun);
        $run = $cancel->handle($aiAgentRun);

        return response()->json([
            'data' => (new AiAgentRunResource($run->load('steps')))->resolve($request),
        ]);
    }
}
