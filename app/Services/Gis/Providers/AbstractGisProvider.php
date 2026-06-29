<?php

namespace App\Services\Gis\Providers;

use App\Services\Gis\DTO\DistanceMatrixResult;
use App\Services\Gis\Exceptions\GisException;
use App\Services\Gis\Exceptions\GisProviderNotConfiguredException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractGisProvider
{
    protected function timeout(): int
    {
        return max((int) config('gis.request_timeout', 10), 1);
    }

    protected function requireApiKey(?string $apiKey, string $providerLabel): string
    {
        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            throw new GisProviderNotConfiguredException("API-ключ {$providerLabel} не задан.");
        }

        return $apiKey;
    }

    protected function providerRequestFailed(string $provider, ?Response $response, float $startedAt, ?Throwable $throwable = null): GisException
    {
        $message = $throwable?->getMessage()
            ?: $this->extractProviderError($response)
            ?: 'GIS-провайдер вернул ошибку.';

        Log::warning('GIS provider request failed.', [
            'provider' => $provider,
            'status' => $response?->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error_message' => $message,
        ]);

        return new GisException($message, $response?->status() ?: 502, $throwable);
    }

    protected function requestSummary(string $provider, Response $response, float $startedAt, array $extra = []): array
    {
        return [
            'provider' => $provider,
            'status' => $response->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ...$extra,
        ];
    }

    protected function notImplementedMatrix(string $provider): DistanceMatrixResult
    {
        return DistanceMatrixResult::notImplemented($provider);
    }

    private function extractProviderError(?Response $response): ?string
    {
        if (! $response) {
            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            return $response->reason();
        }

        return data_get($json, 'error.message')
            ?: data_get($json, 'error')
            ?: data_get($json, 'meta.error.message')
            ?: data_get($json, 'message')
            ?: $response->reason();
    }
}
