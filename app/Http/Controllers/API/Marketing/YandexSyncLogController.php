<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\YandexSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YandexSyncLogController extends Controller
{
    private const MAX_DIALOG_PAYLOAD_CHARS = 120000;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        $query = YandexSyncLog::query()
            ->select([
                'id',
                'yandex_account_id',
                'entity_type',
                'entity_id',
                'action',
                'status',
                'created_at',
                'updated_at',
            ])
            ->selectRaw('LEFT(error_message, 600) as error_message')
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

    public function show(int $log): JsonResponse
    {
        $log = YandexSyncLog::query()
            ->select([
                'id',
                'yandex_account_id',
                'entity_type',
                'entity_id',
                'action',
                'status',
                'request_payload',
                'response_payload',
                'created_at',
                'updated_at',
            ])
            ->selectRaw('LEFT(error_message, 120000) as error_message')
            ->findOrFail($log);

        $log->load('account:id,name,yandex_login,direct_client_login');

        return response()->json([
            'id' => $log->id,
            'account' => $log->account,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'action' => $log->action,
            'status' => $log->status,
            'request_payload' => $this->payloadForDialog($log->request_payload),
            'response_payload' => $this->payloadForDialog($log->response_payload),
            'error_message' => $log->error_message,
            'created_at' => $log->created_at,
            'updated_at' => $log->updated_at,
        ]);
    }

    private function payloadForDialog(mixed $payload): mixed
    {
        if ($payload === null) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || mb_strlen($json) <= self::MAX_DIALOG_PAYLOAD_CHARS) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_message' => 'Payload слишком большой для безопасного отображения целиком. Показан текстовый фрагмент.',
            '_chars' => mb_strlen($json),
            '_preview' => mb_substr($json, 0, self::MAX_DIALOG_PAYLOAD_CHARS),
        ];
    }
}
