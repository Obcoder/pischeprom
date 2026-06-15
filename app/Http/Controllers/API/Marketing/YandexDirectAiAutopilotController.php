<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Jobs\DirectAiAutopilotJob;
use App\Models\YandexAccount;
use App\Models\YandexDirectAiDecision;
use App\Services\Yandex\AI\DirectAiAutopilotEngine;
use App\Services\Yandex\AI\DirectAiPerformanceAnalyzer;
use App\Services\Yandex\AI\DirectAiSafetyGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YandexDirectAiAutopilotController extends Controller
{
    public function dashboard(Request $request, DirectAiPerformanceAnalyzer $performanceAnalyzer): JsonResponse
    {
        $account = $this->accountFromRequest($request);
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        $decisionsQuery = YandexDirectAiDecision::query()
            ->with(['good:id,name,slug,ava_thumb', 'account:id,name,yandex_login,direct_client_login'])
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->latest();

        $paginator = $decisionsQuery->paginate($perPage);

        return response()->json([
            'mode' => (string) config('yandex.direct.ai_autopilot.mode', 'monitor'),
            'guards' => [
                'full_auto_enabled' => (bool) config('yandex.direct.ai_autopilot.full_auto_enabled', false),
                'min_clicks_threshold' => (int) config('yandex.direct.ai_autopilot.min_clicks_threshold', 30),
                'min_learning_days' => (int) config('yandex.direct.ai_autopilot.min_learning_days', 3),
                'cooldown_hours' => (int) config('yandex.direct.ai_autopilot.cooldown_hours', 24),
                'max_daily_spend' => (float) config('yandex.direct.ai_autopilot.max_daily_spend', 1500),
                'max_daily_budget' => (float) config('yandex.direct.auto_launch.max_daily_budget', 500),
            ],
            'summary' => $this->summary($account),
            'live_decisions' => [
                'data' => $paginator->items(),
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'active_campaigns_health' => $performanceAnalyzer->aggregateByGood($account)->take(50)->values()->all(),
            'waste_detector' => $performanceAnalyzer->detectWaste($account),
            'winners' => $performanceAnalyzer->detectWinningAds($account),
            'underperforming_ads' => $performanceAnalyzer->detectUnderperformingAds($account),
            'action_queue' => YandexDirectAiDecision::query()
                ->with(['good:id,name,slug,ava_thumb', 'account:id,name,yandex_login,direct_client_login'])
                ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id))
                ->whereIn('status', [
                    YandexDirectAiDecision::STATUS_PENDING,
                    YandexDirectAiDecision::STATUS_APPROVED,
                ])
                ->latest()
                ->limit(25)
                ->get(),
        ]);
    }

    public function run(Request $request, DirectAiAutopilotEngine $engine): JsonResponse
    {
        $validated = $request->validate([
            'yandex_account_id' => ['nullable', 'integer', 'exists:yandex_accounts,id'],
            'queue' => ['nullable', 'boolean'],
        ]);

        $account = isset($validated['yandex_account_id'])
            ? YandexAccount::query()->findOrFail($validated['yandex_account_id'])
            : null;

        if ($request->boolean('queue')) {
            DirectAiAutopilotJob::dispatch($account?->id);

            return response()->json([
                'queued' => true,
                'account_id' => $account?->id,
            ]);
        }

        return response()->json($engine->processCycle($account));
    }

    public function approve(YandexDirectAiDecision $decision, DirectAiSafetyGateService $safetyGate): JsonResponse
    {
        $safety = $safetyGate->inspect($decision, 'assist');

        if (!($safety['ok'] ?? false)) {
            return response()->json([
                'message' => 'Safety gate blocked approval.',
                'safety' => $safety,
            ], 422);
        }

        $decision->update([
            'status' => YandexDirectAiDecision::STATUS_APPROVED,
            'reason' => [
                ...($decision->reason ?: []),
                'manual_approval' => [
                    'approved_at' => now()->toISOString(),
                    'safety' => $safety,
                ],
            ],
        ]);

        return response()->json($decision->fresh(['good', 'account']));
    }

    public function reject(Request $request, YandexDirectAiDecision $decision): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $decision->update([
            'status' => YandexDirectAiDecision::STATUS_REJECTED,
            'reason' => [
                ...($decision->reason ?: []),
                'manual_rejection' => [
                    'rejected_at' => now()->toISOString(),
                    'comment' => $validated['comment'] ?? null,
                ],
            ],
        ]);

        return response()->json($decision->fresh(['good', 'account']));
    }

    private function accountFromRequest(Request $request): ?YandexAccount
    {
        $accountId = $request->input('yandex_account_id');

        return filled($accountId) ? YandexAccount::query()->find((int) $accountId) : null;
    }

    private function summary(?YandexAccount $account): array
    {
        $base = YandexDirectAiDecision::query()
            ->when($account, fn ($query) => $query->where('yandex_account_id', $account->id));

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', YandexDirectAiDecision::STATUS_PENDING)->count(),
            'approved' => (clone $base)->where('status', YandexDirectAiDecision::STATUS_APPROVED)->count(),
            'rejected' => (clone $base)->where('status', YandexDirectAiDecision::STATUS_REJECTED)->count(),
            'executed' => (clone $base)->where('status', YandexDirectAiDecision::STATUS_EXECUTED)->count(),
            'failed' => (clone $base)->where('status', YandexDirectAiDecision::STATUS_FAILED)->count(),
            'high_risk' => (clone $base)->where('risk_level', YandexDirectAiDecision::RISK_HIGH)->count(),
        ];
    }
}
