<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Domain\AiSales\Services\AiKillSwitchService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\UpdateAiKillSwitchRequest;
use App\Http\Resources\AiSales\AiProviderCapabilityResource;
use App\Http\Resources\AiSales\AiResidencyVerificationResource;
use App\Models\AiAgentDefinition;
use App\Models\AiAgentRun;
use App\Models\AiModelResidencyVerification;
use App\Models\AiProviderCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiControlPlaneController extends Controller
{
    public function show(
        Request $request,
        AiControlPlaneAuthorizationService $authorization,
        AiKillSwitchService $killSwitches,
        UnitContextAuthorizationService $unitAuthorization,
    ): JsonResponse {
        abort_unless($authorization->canView($request->user()), 403);
        $canViewCapabilities = $authorization->canViewCapabilities($request->user());
        $visibleLanes = collect(\App\Domain\AiSales\Enums\BusinessLane::cases())
            ->filter(fn ($lane) => $unitAuthorization->canViewLane($request->user(), $lane))
            ->pluck('value');

        return response()->json(['data' => [
            'mode' => 'stage04_fake_only',
            'features' => [
                'enabled' => (bool) config('ai-sales.enabled', false),
                'fake_execution_enabled' => (bool) config('ai-sales.fake_execution_enabled', false),
                'external_http_enabled' => (bool) config('ai-sales.external_calls_enabled', false),
                'local_ru_enabled' => (bool) config('ai-sales.local_ru_calls_enabled', false),
                'external_sanitized_enabled' => (bool) config('ai-sales.external_sanitized_calls_enabled', false),
                'failover_enabled' => (bool) config('ai-sales.provider_failover_enabled', false),
                'web_search_enabled' => (bool) config('ai-sales.web_search_enabled', false),
                'outreach_sending_enabled' => (bool) config('ai-sales.outreach_sending_enabled', false),
            ],
            'kill_switches' => $killSwitches->all(),
            'counts' => [
                'definitions' => AiAgentDefinition::query()->count(),
                'enabled_definitions' => AiAgentDefinition::query()->where('enabled', true)->count(),
                'runs' => AiAgentRun::query()
                    ->whereIn('lane', $visibleLanes)
                    ->whereHas('businessContext', fn ($query) => $query
                        ->whereIn('lane', $visibleLanes)
                        ->whereColumn('unit_business_contexts.lane', 'ai_agent_runs.lane'))
                    ->count(),
            ],
            'capabilities' => $canViewCapabilities
                ? AiProviderCapabilityResource::collection(AiProviderCapability::query()->orderBy('provider_code')->orderBy('model_id')->orderBy('capability')->limit(200)->get())->resolve($request)
                : [],
            'residency_verifications' => $canViewCapabilities
                ? AiResidencyVerificationResource::collection(AiModelResidencyVerification::query()->orderBy('provider_code')->orderBy('model_id')->limit(100)->get())->resolve($request)
                : [],
            'permissions' => [
                'manage_kill_switches' => $authorization->canManage($request->user()),
                'view_capabilities' => $canViewCapabilities,
            ],
        ]]);
    }

    public function updateKillSwitch(
        UpdateAiKillSwitchRequest $request,
        string $scope,
        AiControlPlaneAuthorizationService $authorization,
        AiKillSwitchService $killSwitches,
    ): JsonResponse {
        abort_unless($authorization->canManage($request->user()), 403);

        return response()->json(['data' => [
            'kill_switches' => $killSwitches->set($scope, $request->boolean('enabled'), $request->user()),
        ]]);
    }
}
