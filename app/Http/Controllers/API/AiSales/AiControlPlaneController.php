<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Domain\AiSales\Services\AiKillSwitchService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\UpdateAiKillSwitchRequest;
use App\Http\Resources\AiSales\AiProviderCapabilityResource;
use App\Http\Resources\AiSales\AiProviderModelResource;
use App\Http\Resources\AiSales\AiResidencyVerificationResource;
use App\Infrastructure\AiSales\Timeweb\TimewebAiGatewayConfiguration;
use App\Models\AiAgentDefinition;
use App\Models\AiAgentRun;
use App\Models\AiModelResidencyVerification;
use App\Models\AiProviderCapability;
use App\Models\AiProviderModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiControlPlaneController extends Controller
{
    public function show(
        Request $request,
        AiControlPlaneAuthorizationService $authorization,
        AiKillSwitchService $killSwitches,
        UnitContextAuthorizationService $unitAuthorization,
        TimewebAiGatewayConfiguration $timeweb,
    ): JsonResponse {
        abort_unless($authorization->canView($request->user()), 403);
        $canViewCapabilities = $authorization->canViewCapabilities($request->user());
        $visibleLanes = collect(\App\Domain\AiSales\Enums\BusinessLane::cases())
            ->filter(fn ($lane) => $unitAuthorization->canViewLane($request->user(), $lane))
            ->pluck('value');

        return response()->json(['data' => [
            'mode' => (string) config('ai-sales.transport_mode', 'fake_only'),
            'features' => [
                'enabled' => (bool) config('ai-sales.enabled', false),
                'fake_execution_enabled' => (bool) config('ai-sales.fake_execution_enabled', false),
                'external_http_enabled' => (bool) config('ai-sales.external_calls_enabled', false),
                'local_ru_enabled' => (bool) config('ai-sales.local_ru_calls_enabled', false),
                'external_sanitized_enabled' => (bool) config('ai-sales.external_sanitized_calls_enabled', false),
                'failover_enabled' => (bool) config('ai-sales.provider_failover_enabled', false),
                'web_search_enabled' => (bool) config('ai-sales.web_search_enabled', false),
                'outreach_sending_enabled' => (bool) config('ai-sales.outreach_sending_enabled', false),
                'timeweb_enabled' => (bool) config('ai-sales.providers.timeweb.enabled', false),
                'timeweb_probe_enabled' => (bool) config('ai-sales.providers.timeweb.probe.enabled', false),
                'timeweb_synthetic_only' => (bool) config('ai-sales.providers.timeweb.probe.synthetic_only', true),
                'tools_enabled' => (bool) config('ai-sales.tools.enabled', false),
                'workflows_enabled' => (bool) config('ai-sales.workflows.enabled', false),
                'provider_native_tools_enabled' => (bool) config('ai-sales.provider_native_tools_enabled', false),
                'live_business_workflows_enabled' => (bool) config('ai-sales.live_business_workflows_enabled', false),
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
            'provider_models' => $canViewCapabilities
                ? AiProviderModelResource::collection(AiProviderModel::query()
                    ->where('provider_code', 'timeweb')
                    ->orderBy('provider_route')
                    ->orderBy('model_id')
                    ->limit(500)
                    ->get())->resolve($request)
                : [],
            'residency_verifications' => $canViewCapabilities
                ? AiResidencyVerificationResource::collection(AiModelResidencyVerification::query()->orderBy('provider_code')->orderBy('model_id')->limit(100)->get())->resolve($request)
                : [],
            'timeweb' => $canViewCapabilities ? [
                'base_url' => 'https://api.timeweb.ai/v1',
                'local_ru' => [
                    'enabled' => (bool) config('ai-sales.providers.timeweb.routes.local_ru.enabled', false),
                    'key_configured' => $timeweb->fingerprint(\App\Domain\AiSales\Enums\AiProviderRoute::LocalRu) !== null,
                    'key_fingerprint_suffix' => $timeweb->fingerprint(\App\Domain\AiSales\Enums\AiProviderRoute::LocalRu),
                ],
                'external_sanitized' => [
                    'enabled' => (bool) config('ai-sales.providers.timeweb.routes.external_sanitized.enabled', false),
                    'key_configured' => $timeweb->fingerprint(\App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized) !== null,
                    'key_fingerprint_suffix' => $timeweb->fingerprint(\App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized),
                ],
                'probe_enabled' => (bool) config('ai-sales.providers.timeweb.probe.enabled', false),
                'synthetic_only' => (bool) config('ai-sales.providers.timeweb.probe.synthetic_only', true),
            ] : null,
            'permissions' => [
                'manage_kill_switches' => $authorization->canManage($request->user()),
                'view_capabilities' => $canViewCapabilities,
                'view_tooling' => $authorization->canViewTooling($request->user()),
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
