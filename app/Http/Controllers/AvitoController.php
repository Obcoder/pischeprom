<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoApiCall;
use App\Models\AvitoCapabilitySetting;
use App\Models\AvitoConnection;
use App\Models\AvitoWebhookEvent;
use App\Services\Avito\AvitoApiExecutor;
use App\Services\Avito\AvitoTokenManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AvitoController extends Controller
{
    public function status(AvitoApiCatalog $catalog, AvitoTokenManager $tokens): JsonResponse
    {
        $metadata = $catalog->metadata();
        $configured = $tokens->clientCredentialsConfigured();

        return response()->json([
            'enabled' => (bool) config('avito.enabled'),
            'configured' => $configured,
            'mutations_enabled' => (bool) config('avito.mutations_enabled'),
            'webhook_protected' => filled(config('avito.webhook_secret')),
            'oauth_redirect_uri' => $tokens->redirectUri(),
            'webhook_url' => route('api.avito.webhook'),
            'documentation_url' => config('avito.documentation_url'),
            'oauth_scopes' => config('avito.oauth_scopes'),
            'missing_environment' => $configured ? [] : array_values(array_filter([
                blank(config('avito.client_id')) ? 'AVITO_CLIENT_ID' : null,
                blank(config('avito.client_secret')) ? 'AVITO_CLIENT_SECRET' : null,
            ])),
            'catalog' => [
                'generated_at' => $metadata['generated_at'],
                'source_hash' => $metadata['source_hash'],
                'counts' => $metadata['counts'],
                'sections' => $metadata['sections'],
            ],
            'connections' => AvitoConnection::query()->count(),
            'active_connections' => AvitoConnection::query()->where('is_active', true)->count(),
            'disabled_capabilities' => AvitoCapabilitySetting::query()->where('enabled', false)->count(),
            'last_call' => $this->serializeCall(AvitoApiCall::query()->latest('id')->first()),
        ]);
    }

    public function capabilities(Request $request, AvitoApiCatalog $catalog): JsonResponse
    {
        $settings = AvitoCapabilitySetting::query()->get()->keyBy('capability_id');
        $lastCalls = AvitoApiCall::query()
            ->latest('id')
            ->get(['id', 'capability_id', 'status', 'http_status', 'created_at'])
            ->unique('capability_id')
            ->keyBy('capability_id');
        $search = Str::lower(trim($request->string('search')->toString()));
        $section = $request->string('section')->toString();
        $method = Str::upper($request->string('method')->toString());
        $access = $request->string('access')->toString();

        $items = collect($catalog->capabilities())
            ->when($search !== '', fn ($items) => $items->filter(fn (array $item) => Str::contains(
                Str::lower(implode(' ', [$item['summary'], $item['description'], $item['path'], $item['operation_id'], $item['section_title']])),
                $search
            )))
            ->when($section !== '', fn ($items) => $items->where('section', $section))
            ->when($method !== '', fn ($items) => $items->where('method', $method))
            ->when($access !== '', fn ($items) => $items->where('access', $access))
            ->values()
            ->map(function (array $item) use ($settings, $lastCalls) {
                $setting = $settings->get($item['id']);
                $lastCall = $lastCalls->get($item['id']);

                return Arr::only($item, [
                    'id', 'section', 'section_title', 'operation_id', 'method', 'path',
                    'summary', 'tags', 'security', 'deprecated', 'access', 'risk',
                    'requires_confirmation', 'managed_by_integration', 'documentation_url', 'also_listed_in',
                ]) + [
                    'enabled' => $setting ? $setting->enabled : ! $item['deprecated'],
                    'notes' => $setting?->notes,
                    'parameter_count' => count($item['parameters']),
                    'has_request_body' => $item['request_body'] !== null,
                    'last_status' => $lastCall?->status ?: $setting?->last_status,
                    'last_http_status' => $lastCall?->http_status,
                    'last_used_at' => $lastCall?->created_at ?: $setting?->last_used_at,
                ];
            });

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
            'catalog_total' => count($catalog->capabilities()),
        ]);
    }

    public function capability(string $capability, AvitoApiCatalog $catalog): JsonResponse
    {
        $item = $catalog->find($capability);
        $setting = AvitoCapabilitySetting::query()->where('capability_id', $capability)->first();

        return response()->json([
            'capability' => $item + [
                'enabled' => $setting ? $setting->enabled : ! $item['deprecated'],
                'notes' => $setting?->notes,
            ],
        ]);
    }

    public function updateCapability(Request $request, string $capability, AvitoApiCatalog $catalog): JsonResponse
    {
        $catalog->find($capability);
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);
        $setting = AvitoCapabilitySetting::query()->updateOrCreate(
            ['capability_id' => $capability],
            $validated
        );

        return response()->json([
            'setting' => [
                'capability_id' => $setting->capability_id,
                'enabled' => $setting->enabled,
                'notes' => $setting->notes,
            ],
        ]);
    }

    public function bulkUpdateCapabilities(Request $request, AvitoApiCatalog $catalog): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:250'],
            'ids.*' => ['required', 'string', 'max:120', 'distinct'],
            'enabled' => ['required', 'boolean'],
        ]);
        $known = collect($catalog->capabilities())->pluck('id')->flip();

        foreach ($validated['ids'] as $id) {
            if (! $known->has($id)) {
                throw new AvitoException('В списке есть неизвестная функция.', 'capability_not_found', 404);
            }

            AvitoCapabilitySetting::query()->updateOrCreate(
                ['capability_id' => $id],
                ['enabled' => $validated['enabled']]
            );
        }

        return response()->json(['updated' => count($validated['ids'])]);
    }

    public function execute(
        Request $request,
        string $capability,
        AvitoApiExecutor $executor,
    ): JsonResponse {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
            'path' => ['sometimes', 'array'],
            'query' => ['sometimes', 'array'],
            'headers' => ['sometimes', 'array'],
            'body' => ['nullable'],
            'content_type' => ['nullable', 'string', 'max:120'],
            'confirmation' => ['nullable', 'string', 'max:120'],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:24576'],
        ]);
        if (is_string($validated['body'] ?? null)) {
            $decodedBody = json_decode($validated['body'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AvitoException('Тело multipart-запроса содержит некорректный JSON.', 'validation', 422);
            }

            $validated['body'] = $decodedBody;
        }
        $connection = isset($validated['connection_id'])
            ? AvitoConnection::query()->findOrFail($validated['connection_id'])
            : null;

        $result = $executor->execute(
            $capability,
            $validated,
            $connection,
            (array) $request->file('files', [])
        );

        return response()->json($result);
    }

    public function preflight(Request $request, AvitoApiExecutor $executor): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);
        $connection = isset($validated['connection_id'])
            ? AvitoConnection::query()->findOrFail($validated['connection_id'])
            : null;
        $result = $executor->execute('user.getuserinfoself.4f59f9b2ea', [], $connection);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Соединение с Avito работает.' : 'Avito отклонил проверочный запрос.',
            'result' => $result,
        ]);
    }

    public function connections(): JsonResponse
    {
        return response()->json([
            'items' => AvitoConnection::query()->latest('id')->get()->map(fn (AvitoConnection $connection) => $this->serializeConnection($connection)),
        ]);
    }

    public function refreshConnection(AvitoConnection $connection, AvitoTokenManager $tokens): JsonResponse
    {
        $connection = $tokens->refresh($connection);

        return response()->json([
            'connection' => $this->serializeConnection($connection),
            'message' => 'OAuth-токен Avito обновлён.',
        ]);
    }

    public function destroyConnection(AvitoConnection $connection): JsonResponse
    {
        $connection->delete();

        return response()->json(['message' => 'OAuth-подключение и его токены удалены.']);
    }

    public function oauthRedirect(Request $request, AvitoTokenManager $tokens): RedirectResponse
    {
        $state = Str::random(48);
        $request->session()->put('avito_oauth_state', $state);

        return redirect()->away($tokens->authorizationUrl($state));
    }

    public function oauthCallback(Request $request, AvitoTokenManager $tokens): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('Ameise.avito', ['oauth' => 'denied']);
        }

        $request->validate([
            'code' => ['required', 'string', 'max:2048'],
            'state' => ['required', 'string', 'max:256'],
        ]);
        $expected = (string) $request->session()->pull('avito_oauth_state');
        $actual = $request->string('state')->toString();

        if ($expected === '' || ! hash_equals($expected, $actual)) {
            abort(419, 'Сессия подключения Avito истекла. Повторите подключение.');
        }

        try {
            $tokens->exchangeAuthorizationCode($request->string('code')->toString());
        } catch (AvitoException) {
            return redirect()->route('Ameise.avito', ['oauth' => 'error']);
        }

        return redirect()->route('Ameise.avito', ['oauth' => 'success']);
    }

    public function calls(Request $request): JsonResponse
    {
        $query = AvitoApiCall::query()->with('connection:id,name')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('capability_id')) {
            $query->where('capability_id', $request->string('capability_id')->toString());
        }

        $calls = $query->paginate(min(100, max(10, $request->integer('per_page', 30))));

        return response()->json($this->mapPaginator($calls, fn (AvitoApiCall $call) => $this->serializeCall($call)));
    }

    public function call(AvitoApiCall $call): JsonResponse
    {
        return response()->json([
            'call' => $this->serializeCall($call) + [
                'request_meta' => $call->request_meta,
                'response_meta' => $call->response_meta,
            ],
        ]);
    }

    public function webhooks(Request $request): JsonResponse
    {
        $events = AvitoWebhookEvent::query()
            ->latest('id')
            ->paginate(min(100, max(10, $request->integer('per_page', 30))));

        return response()->json($this->mapPaginator($events, fn (AvitoWebhookEvent $event) => $this->serializeWebhook($event)));
    }

    public function webhook(AvitoWebhookEvent $event): JsonResponse
    {
        return response()->json([
            'event' => $this->serializeWebhook($event) + ['payload' => $event->payload],
        ]);
    }

    public function receiveWebhook(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('avito.webhook_secret');
        $actualSecret = (string) ($request->header('X-Secret')
            ?: $request->header('X-Avito-Webhook-Secret')
            ?: $request->query('secret', ''));

        if ($expectedSecret === '') {
            return response()->json(['message' => 'Webhook Avito отключён: AVITO_WEBHOOK_SECRET не задан.'], 503);
        }

        if ($actualSecret === '' || ! hash_equals($expectedSecret, $actualSecret)) {
            return response()->json(['message' => 'Webhook secret не принят.'], 401);
        }

        $payload = $request->json()->all();
        $externalId = (string) (Arr::get($payload, 'id')
            ?: Arr::get($payload, 'event_id')
            ?: Arr::get($payload, 'applyId')
            ?: Arr::get($payload, 'payload.value.id')
            ?: '');
        $type = (string) (Arr::get($payload, 'type')
            ?: Arr::get($payload, 'event_type')
            ?: Arr::get($payload, 'payload.type')
            ?: Arr::get($payload, 'payload.value.type')
            ?: (Arr::has($payload, 'applyId') ? 'job.application' : null)
            ?: 'unknown');
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $deduplicationKey = hash('sha256', $type.'|'.$externalId.'|'.$encoded);
        $event = AvitoWebhookEvent::query()->firstOrCreate(
            ['deduplication_key' => $deduplicationKey],
            [
                'external_event_id' => $externalId !== '' ? $externalId : null,
                'event_type' => $type,
                'payload' => $payload,
                'status' => 'received',
                'received_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'duplicate' => ! $event->wasRecentlyCreated,
            'event_id' => $event->id,
        ], $event->wasRecentlyCreated ? 202 : 200);
    }

    private function serializeConnection(AvitoConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'auth_mode' => $connection->auth_mode,
            'external_user_id' => $connection->external_user_id,
            'token_expires_at' => $connection->token_expires_at,
            'scopes' => $connection->scopes ?: [],
            'status' => $connection->status,
            'is_active' => $connection->is_active,
            'last_checked_at' => $connection->last_checked_at,
            'last_error' => $connection->last_error,
            'has_access_token' => filled($connection->access_token),
            'has_refresh_token' => filled($connection->refresh_token),
            'created_at' => $connection->created_at,
        ];
    }

    private function serializeCall(?AvitoApiCall $call): ?array
    {
        if (! $call) {
            return null;
        }

        return [
            'id' => $call->id,
            'request_id' => $call->request_id,
            'capability_id' => $call->capability_id,
            'method' => $call->method,
            'endpoint' => $call->endpoint,
            'status' => $call->status,
            'http_status' => $call->http_status,
            'duration_ms' => $call->duration_ms,
            'error_message' => $call->error_message,
            'connection' => $call->relationLoaded('connection') ? $call->connection?->only(['id', 'name']) : null,
            'created_at' => $call->created_at,
        ];
    }

    private function serializeWebhook(AvitoWebhookEvent $event): array
    {
        return [
            'id' => $event->id,
            'external_event_id' => $event->external_event_id,
            'event_type' => $event->event_type,
            'status' => $event->status,
            'received_at' => $event->received_at,
            'processed_at' => $event->processed_at,
            'error_message' => $event->error_message,
        ];
    }

    private function mapPaginator(LengthAwarePaginator $paginator, callable $callback): array
    {
        return [
            'data' => $paginator->getCollection()->map($callback)->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
