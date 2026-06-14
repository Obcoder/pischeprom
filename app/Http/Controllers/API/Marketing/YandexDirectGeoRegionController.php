<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\YandexAccount;
use App\Services\Yandex\YandexDirectGeoRegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class YandexDirectGeoRegionController extends Controller
{
    public function index(Request $request, YandexDirectGeoRegionService $service): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'remote' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'integer', 'exists:yandex_accounts,id'],
        ]);

        $account = $this->account($validated['account_id'] ?? null);

        try {
            return response()->json($service->search(
                (string) ($validated['search'] ?? ''),
                (int) ($validated['limit'] ?? 40),
                (bool) ($validated['remote'] ?? true),
                $account,
            ));
        } catch (Throwable $e) {
            return response()->json([
                'items' => [],
                'source' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Request $request, YandexDirectGeoRegionService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'min:1'],
            'remote' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'integer', 'exists:yandex_accounts,id'],
        ]);

        try {
            return response()->json([
                'items' => $service->findByIds(
                    $validated['ids'],
                    (bool) ($validated['remote'] ?? true),
                    $this->account($validated['account_id'] ?? null),
                ),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'items' => [],
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sync(Request $request, YandexDirectGeoRegionService $service): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:yandex_accounts,id'],
        ]);

        try {
            return response()->json($service->syncAll($this->account($validated['account_id'] ?? null)));
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function account(?int $accountId): ?YandexAccount
    {
        return $accountId ? YandexAccount::query()->findOrFail($accountId) : null;
    }
}
