<?php

namespace App\Services\Yandex;

use App\Models\YandexAccount;
use App\Models\YandexSyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexDirectApiClient
{
    public function __construct(private readonly YandexOAuthService $oauthService) {}

    /**
     * @throws ConnectionException
     */
    public function request(YandexAccount $account, string $service, string $method, array $payload = []): array
    {
        $account = $this->oauthService->ensureFreshToken($account);

        if (blank($account->access_token)) {
            throw new RuntimeException('Не подключён OAuth-токен Яндекса.');
        }

        $body = [
            'method' => $method,
            'params' => $payload,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $account->access_token,
            'Accept-Language' => 'ru',
            'Content-Type' => 'application/json; charset=utf-8',
        ];

        if (filled($account->direct_client_login)) {
            $headers['Client-Login'] = $account->direct_client_login;
        }

        $url = rtrim(config('yandex.direct.api_url'), '/') . '/' . trim($service, '/');

        $log = YandexSyncLog::query()->create([
            'yandex_account_id' => $account->id,
            'entity_type' => 'direct_api',
            'action' => "{$service}.{$method}",
            'status' => 'request',
            'request_payload' => $body,
        ]);

        $response = Http::withHeaders($headers)
            ->timeout((int) config('yandex.direct.timeout', 20))
            ->post($url, $body);

        $json = $response->json() ?: [];
        $error = Arr::get($json, 'error.error_detail')
            ?: Arr::get($json, 'error.error_string')
            ?: Arr::get($json, 'error.message');

        if (!$response->successful() || Arr::has($json, 'error')) {
            $log->update([
                'status' => 'error',
                'response_payload' => $json,
                'error_message' => $error ?: 'Yandex Direct API request failed.',
            ]);

            throw new RuntimeException($error ?: 'Ошибка API Яндекс.Директа.');
        }

        $log->update([
            'status' => 'success',
            'response_payload' => $json,
        ]);

        return $json;
    }
}
