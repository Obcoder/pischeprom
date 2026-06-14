<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\YandexSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YandexSyncLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        $query = YandexSyncLog::query()
            ->with('account:id,name,yandex_login,direct_client_login')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
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
}
