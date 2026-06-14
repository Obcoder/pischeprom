<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\DirectLaunchSession;
use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectDailyStat;
use App\Services\Yandex\DirectAutoLauncherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class YandexDirectLaunchController extends Controller
{
    public function launch(Request $request, Good $good, DirectAutoLauncherService $service): JsonResponse
    {
        $validated = $request->validate([
            'yandex_account_id' => ['nullable', 'integer', 'exists:yandex_accounts,id'],
            'dry_run' => ['nullable', 'boolean'],
            'budget_approved' => ['nullable', 'boolean'],
            'daily_budget' => ['nullable', 'numeric', 'min:1'],
        ]);

        $account = isset($validated['yandex_account_id'])
            ? YandexAccount::query()->findOrFail($validated['yandex_account_id'])
            : null;

        try {
            $result = $service->launch($good, $account, $validated);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $status = in_array($result['status'] ?? null, [DirectLaunchSession::STATUS_FAILED, DirectLaunchSession::STATUS_PARTIAL], true)
            ? 422
            : 200;

        return response()->json($result, $status);
    }

    public function sessions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $query = DirectLaunchSession::query()
            ->with(['good:id,name,slug,ava_thumb', 'account:id,name,yandex_login,direct_client_login'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('good_id'), fn ($q, $id) => $q->where('good_id', $id))
            ->latest();

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $total = DirectLaunchSession::query()->count();
        $success = DirectLaunchSession::query()->where('status', DirectLaunchSession::STATUS_SUCCESS)->count();
        $dryRun = DirectLaunchSession::query()->where('status', DirectLaunchSession::STATUS_DRY_RUN)->count();
        $failed = DirectLaunchSession::query()->where('status', DirectLaunchSession::STATUS_FAILED)->count();
        $partial = DirectLaunchSession::query()->where('status', DirectLaunchSession::STATUS_PARTIAL)->count();
        $rollbackCount = DirectLaunchSession::query()->whereNotNull('rollback_payload')->count();
        $spend = (float) YandexDirectDailyStat::query()->sum('cost');

        return response()->json([
            'summary' => [
                'total' => $total,
                'success' => $success,
                'dry_run' => $dryRun,
                'failed' => $failed,
                'partial' => $partial,
                'rollback_count' => $rollbackCount,
                'success_rate' => $total > 0 ? round(($success + $dryRun) / $total * 100, 2) : null,
                'spend' => $spend,
                'roi' => null,
            ],
            'last_launches' => DirectLaunchSession::query()
                ->with(['good:id,name,slug,ava_thumb', 'account:id,name,yandex_login,direct_client_login'])
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }
}
