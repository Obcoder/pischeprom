<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Domain\AiSales\Tools\AiToolRegistry;
use App\Domain\AiSales\Workflows\AiWorkflowRegistry;
use App\Http\Controllers\Controller;
use App\Models\AiToolCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiToolingDiagnosticsController extends Controller
{
    public function __invoke(
        Request $request,
        AiControlPlaneAuthorizationService $authorization,
        UnitContextAuthorizationService $unitAuthorization,
        AiToolRegistry $tools,
        AiWorkflowRegistry $workflows,
    ): JsonResponse {
        abort_unless($authorization->canViewTooling($request->user()), 403);

        $visibleLanes = collect(BusinessLane::cases())
            ->filter(fn (BusinessLane $lane): bool => $unitAuthorization->canViewLane($request->user(), $lane))
            ->pluck('value');
        $toolsEnabled = (bool) config('ai-sales.tools.enabled', false);
        $workflowsEnabled = (bool) config('ai-sales.workflows.enabled', false);
        $executions = AiToolCall::query()
            ->whereHas('run', fn ($query) => $query
                ->whereIn('lane', $visibleLanes)
                ->whereHas('businessContext', fn ($contextQuery) => $contextQuery
                    ->whereIn('lane', $visibleLanes)
                    ->whereColumn('unit_business_contexts.lane', 'ai_agent_runs.lane')))
            ->with(['run:id,public_id,lane,unit_id,unit_business_context_id'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(static fn (AiToolCall $call): array => [
                'run_id' => $call->run?->public_id,
                'tool_code' => $call->tool_code,
                'tool_version' => $call->tool_version,
                'workflow_code' => $call->workflow_code,
                'workflow_version' => $call->workflow_version,
                'workflow_hash' => $call->workflow_hash,
                'schema_hash' => $call->tool_schema_hash,
                'policy_version' => $call->tool_policy_version,
                'contour' => $call->contour->value,
                'status' => $call->status,
                'authorization_decision' => $call->authorization_decision,
                'row_count' => (int) $call->row_count,
                'byte_count' => (int) $call->byte_count,
                'query_count' => (int) $call->query_count,
                'redaction_count' => (int) $call->redaction_count,
                'duration_ms' => $call->duration_ms === null ? null : (int) $call->duration_ms,
                'block_reason' => $call->safe_error_code,
                'created_at' => $call->created_at?->toISOString(),
                'finished_at' => $call->finished_at?->toISOString(),
            ])
            ->values();

        return response()->json(['data' => [
            'features' => [
                'tools_enabled' => $toolsEnabled,
                'workflows_enabled' => $workflowsEnabled,
                'provider_native_tools_enabled' => (bool) config('ai-sales.provider_native_tools_enabled', false),
                'live_business_workflows_enabled' => (bool) config('ai-sales.live_business_workflows_enabled', false),
                'external_http_enabled' => (bool) config('ai-sales.external_calls_enabled', false),
                'failover_enabled' => (bool) config('ai-sales.provider_failover_enabled', false),
            ],
            'tools' => collect($tools->all())
                ->map(fn ($tool): array => [
                    ...$tool->safeMetadata($toolsEnabled),
                    'block_reason' => ! $toolsEnabled
                        ? 'feature_disabled'
                        : (! $tool->enabled ? 'definition_disabled' : ($tool->liveEligible ? null : 'no_live_business_workflow')),
                ])
                ->values(),
            'workflows' => collect($workflows->all())
                ->map(fn ($workflow): array => [
                    ...$workflow->safeMetadata($workflowsEnabled),
                    'block_reason' => ! $workflowsEnabled ? 'feature_disabled' : 'development_test_only',
                ])
                ->values(),
            'executions' => $executions,
            'manual_execution' => 'synthetic_cli_or_tests_only',
        ]]);
    }
}
