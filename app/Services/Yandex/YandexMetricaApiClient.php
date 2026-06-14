<?php

namespace App\Services\Yandex;

use App\Models\YandexAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexMetricaApiClient
{
    public function __construct(private readonly YandexOAuthService $oauthService) {}

    /**
     * @throws ConnectionException
     */
    public function request(YandexAccount $account, string $path, array $query = []): array
    {
        $account = $this->oauthService->ensureFreshToken($account);

        if (blank($account->access_token)) {
            throw new RuntimeException('Не подключён OAuth-токен Яндекса.');
        }

        $response = Http::withToken($account->access_token)
            ->timeout((int) config('yandex.metrica.timeout', 20))
            ->acceptJson()
            ->get(rtrim(config('yandex.metrica.api_url'), '/') . '/' . ltrim($path, '/'), $query);

        if (!$response->successful()) {
            throw new RuntimeException('Ошибка API Яндекс.Метрики: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    public function counters(YandexAccount $account): array
    {
        return $this->request($account, '/management/v1/counters');
    }

    public function goals(YandexAccount $account, ?string $counterId = null): array
    {
        $counterId = $counterId ?: $account->metrica_counter_id ?: config('yandex.metrica.counter_id');

        if (blank($counterId)) {
            throw new RuntimeException('Не найден счётчик Метрики.');
        }

        return $this->request($account, "/management/v1/counter/{$counterId}/goals");
    }

    public function utmStats(YandexAccount $account, array $params = []): array
    {
        $counterId = $params['ids'] ?? ($account->metrica_counter_id ?: config('yandex.metrica.counter_id'));

        if (blank($counterId)) {
            throw new RuntimeException('Не найден счётчик Метрики.');
        }

        return $this->request($account, '/stat/v1/data', [
            ...$params,
            'ids' => $counterId,
            'dimensions' => $params['dimensions'] ?? 'ym:s:lastUTMSource,ym:s:lastUTMCampaign,ym:s:lastUTMContent,ym:s:lastUTMTerm',
            'metrics' => $params['metrics'] ?? 'ym:s:visits,ym:s:goalReaches',
        ]);
    }
}
