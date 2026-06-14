<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\YandexAccount;
use App\Services\Yandex\YandexDirectApiClient;
use App\Services\Yandex\YandexOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class YandexAccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'accounts' => YandexAccount::query()
                ->latest()
                ->get()
                ->map(fn (YandexAccount $account) => $this->serialize($account)),
            'integrations' => [
                'yandex_cloud_storage' => [
                    'found' => filled(config('filesystems.disks.yandex.key')) && filled(config('filesystems.disks.yandex.bucket')),
                    'can_use_for_direct' => false,
                    'note' => 'Это S3/Object Storage ключ, не OAuth для Директа или Метрики.',
                ],
                'yandex_search' => [
                    'found' => filled(config('services.yandex_search.api_key')) && filled(config('services.yandex_search.folder_id')),
                    'can_use_for_direct' => false,
                    'note' => 'Это API-ключ Yandex Search API, не OAuth.',
                ],
                'yandex_mail' => [
                    'found' => filled(config('services.yandex_mail.imap.username')) && filled(config('services.yandex_mail.imap.password')),
                    'can_use_for_direct' => false,
                    'note' => 'Это IMAP-доступ к почте, не OAuth для рекламы.',
                ],
                'yandex_metrica_counter' => [
                    'found' => filled(config('yandex.metrica.counter_id')),
                    'counter_id' => config('yandex.metrica.counter_id'),
                    'can_use_for_direct' => false,
                    'note' => 'ID счётчика не даёт прав API. Для Метрики API нужен OAuth.',
                ],
                'yandex_static_maps' => [
                    'found' => filled(env('VITE_YANDEX_STATIC_MAPS_API_KEY')),
                    'can_use_for_direct' => false,
                    'note' => 'Это ключ Static Maps, не OAuth.',
                ],
                'existing_oauth' => [
                    'found' => YandexAccount::query()->exists(),
                    'source' => 'yandex_accounts',
                    'note' => 'Отдельное OAuth-подключение для Директа/Метрики.',
                ],
                'direct_real_send' => [
                    'found' => (bool) config('yandex.direct.enable_real_send'),
                    'can_use_for_direct' => (bool) config('yandex.direct.enable_real_send'),
                    'note' => config('yandex.direct.enable_real_send')
                        ? 'Реальная отправка в Директ включена. Нужен существующий external AdGroupId.'
                        : 'Реальная отправка отключена через YANDEX_DIRECT_ENABLE_REAL_SEND=false.',
                ],
            ],
        ]);
    }

    public function check(YandexAccount $account, YandexOAuthService $service, YandexDirectApiClient $directClient): JsonResponse
    {
        try {
            $check = $service->check($account);
            $direct = $directClient->request($account, 'clients', 'get', [
                'FieldNames' => ['Login', 'ClientId', 'ClientInfo'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'account' => $this->serialize($account->fresh()),
            'check' => [
                ...$check,
                'direct_api' => 'ok',
                'direct_response' => $direct,
            ],
        ]);
    }

    public function update(Request $request, YandexAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'yandex_login' => ['nullable', 'string', 'max:255'],
            'direct_client_login' => ['nullable', 'string', 'max:255'],
            'metrica_counter_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account->update($validated);

        return response()->json($this->serialize($account->fresh()));
    }

    public function destroy(YandexAccount $account): JsonResponse
    {
        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'is_active' => false,
        ]);

        return response()->json($this->serialize($account->fresh()));
    }

    private function serialize(YandexAccount $account): array
    {
        return [
            'id' => $account->id,
            'user_id' => $account->user_id,
            'name' => $account->name,
            'yandex_login' => $account->yandex_login,
            'direct_client_login' => $account->direct_client_login,
            'metrica_counter_id' => $account->metrica_counter_id,
            'token_expires_at' => $account->token_expires_at,
            'scopes' => $account->scopes,
            'is_active' => $account->is_active,
            'last_checked_at' => $account->last_checked_at,
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
            'has_access_token' => filled($account->access_token),
            'has_refresh_token' => filled($account->refresh_token),
        ];
    }
}
