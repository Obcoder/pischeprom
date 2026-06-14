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
    private const MAX_LOG_LIST_ITEMS = 50;
    private const MAX_LOG_STRING_CHARS = 12000;
    private const MAX_LOG_DEPTH = 8;

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
            'request_payload' => $this->payloadForLog($body),
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
                'response_payload' => $this->payloadForLog($json),
                'error_message' => $error ?: 'Yandex Direct API request failed.',
            ]);

            throw new RuntimeException($error ?: 'Ошибка API Яндекс.Директа.');
        }

        $log->update([
            'status' => 'success',
            'response_payload' => $this->payloadForLog($json),
        ]);

        return $json;
    }

    private function payloadForLog(mixed $payload, int $depth = 0): mixed
    {
        if (is_string($payload)) {
            return mb_strlen($payload) > self::MAX_LOG_STRING_CHARS
                ? mb_substr($payload, 0, self::MAX_LOG_STRING_CHARS) . '... [truncated]'
                : $payload;
        }

        if (!is_array($payload)) {
            return $payload;
        }

        if ($depth >= self::MAX_LOG_DEPTH) {
            return [
                '__truncated_depth' => true,
                '__message' => 'Payload nesting is deeper than log limit.',
            ];
        }

        if (array_is_list($payload) && count($payload) > self::MAX_LOG_LIST_ITEMS) {
            return [
                '__truncated_list' => true,
                '__count' => count($payload),
                '__sample' => array_map(
                    fn ($item) => $this->payloadForLog($item, $depth + 1),
                    array_slice($payload, 0, self::MAX_LOG_LIST_ITEMS)
                ),
            ];
        }

        $result = [];
        $index = 0;

        foreach ($payload as $key => $value) {
            if ($index >= self::MAX_LOG_LIST_ITEMS * 4) {
                $result['__truncated_keys'] = true;
                $result['__count'] = count($payload);
                break;
            }

            $result[$key] = $this->payloadForLog($value, $depth + 1);
            $index++;
        }

        return $result;
    }
}
