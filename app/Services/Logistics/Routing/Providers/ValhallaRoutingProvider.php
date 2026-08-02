<?php

namespace App\Services\Logistics\Routing\Providers;

use App\Services\Logistics\Map\GisReleaseMetadataService;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\MatrixCell;
use App\Services\Logistics\Routing\DTO\MatrixRequest;
use App\Services\Logistics\Routing\DTO\MatrixResult;
use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RouteResult;
use App\Services\Logistics\Routing\DTO\RoutingHealth;
use App\Services\Logistics\Routing\Exceptions\MalformedRoutingResponseException;
use App\Services\Logistics\Routing\Exceptions\NoRouteException;
use App\Services\Logistics\Routing\Exceptions\ProviderUnavailableException;
use App\Services\Logistics\Routing\Exceptions\RoutingException;
use App\Services\Logistics\Routing\Support\Polyline6;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ValhallaRoutingProvider implements RoutingProviderInterface
{
    private ?string $engineVersion = null;

    public function __construct(private readonly ?GisReleaseMetadataService $releaseMetadata = null) {}

    public function code(): string
    {
        return 'valhalla';
    }

    public function health(): RoutingHealth
    {
        $startedAt = microtime(true);

        try {
            $response = $this->pendingRequest()->get($this->url('/status'));
            $json = $response->json();
            $version = is_array($json) && is_string($json['version'] ?? null)
                ? $json['version']
                : null;
            $this->engineVersion = $version ?: $this->configuredEngineVersion();

            return new RoutingHealth(
                healthy: $response->successful(),
                provider: $this->code(),
                routingEngineVersion: $this->engineVersion,
                osmDataVersion: $this->osmDataVersion(),
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                message: $response->successful() ? null : 'Routing-сервис вернул ошибку статуса.',
            );
        } catch (Throwable) {
            return new RoutingHealth(
                healthy: false,
                provider: $this->code(),
                routingEngineVersion: $this->configuredEngineVersion(),
                osmDataVersion: $this->osmDataVersion(),
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                message: 'Внутренний routing-сервис недоступен.',
            );
        }
    }

    public function route(RouteRequest $request): RouteResult
    {
        $payload = [
            'locations' => array_map(fn ($point) => $point->toValhalla(), $request->points),
            'costing' => $request->profile->costing,
            'costing_options' => $request->profile->options === []
                ? new \stdClass
                : [$request->profile->costing => $request->profile->options],
            'units' => 'kilometers',
            'language' => 'ru-RU',
            'shape_format' => 'polyline6',
            'id' => $request->requestHash,
        ];

        $response = $this->post('/route', $payload);
        $json = $this->successfulJson($response);
        $summary = data_get($json, 'trip.summary');
        $legs = data_get($json, 'trip.legs');

        if (! is_array($summary) || ! is_numeric($summary['length'] ?? null)
            || ! is_numeric($summary['time'] ?? null) || ! is_array($legs) || $legs === []) {
            throw new MalformedRoutingResponseException;
        }

        $shapes = [];
        foreach ($legs as $leg) {
            if (! is_array($leg) || ! is_string($leg['shape'] ?? null)) {
                throw new MalformedRoutingResponseException;
            }
            $shapes[] = $leg['shape'];
        }

        try {
            $shape = Polyline6::combine($shapes);
        } catch (Throwable) {
            throw new MalformedRoutingResponseException('Routing-сервис вернул некорректную геометрию маршрута.');
        }

        return new RouteResult(
            distanceM: (int) round((float) $summary['length'] * 1000),
            durationS: (int) round((float) $summary['time']),
            shapePolyline6: $shape,
            legs: $legs,
            provider: $this->code(),
            routingEngineVersion: $this->engineVersion ?: $this->configuredEngineVersion(),
            osmDataVersion: $this->osmDataVersion(),
        );
    }

    public function matrix(MatrixRequest $request): MatrixResult
    {
        $payload = [
            'sources' => array_map(fn ($point) => $point->toValhalla(), $request->sources),
            'targets' => array_map(fn ($point) => $point->toValhalla(), $request->targets),
            'costing' => $request->profile->costing,
            'costing_options' => $request->profile->options === []
                ? new \stdClass
                : [$request->profile->costing => $request->profile->options],
            'units' => 'kilometers',
            'verbose' => false,
            'id' => $request->requestHash,
        ];

        $response = $this->post('/sources_to_targets', $payload);
        $json = $this->successfulJson($response);
        $distances = data_get($json, 'sources_to_targets.distances');
        $durations = data_get($json, 'sources_to_targets.durations');

        if (! is_array($distances) || ! is_array($durations)
            || count($distances) !== count($request->sources)
            || count($durations) !== count($request->sources)) {
            throw new MalformedRoutingResponseException;
        }

        $cells = [];
        foreach ($request->sources as $sourceIndex => $_source) {
            if (! is_array($distances[$sourceIndex] ?? null) || ! is_array($durations[$sourceIndex] ?? null)
                || count($distances[$sourceIndex]) !== count($request->targets)
                || count($durations[$sourceIndex]) !== count($request->targets)) {
                throw new MalformedRoutingResponseException;
            }

            foreach ($request->targets as $targetIndex => $_target) {
                $distance = $distances[$sourceIndex][$targetIndex] ?? null;
                $duration = $durations[$sourceIndex][$targetIndex] ?? null;

                if (($distance !== null && ! is_numeric($distance)) || ($duration !== null && ! is_numeric($duration))) {
                    throw new MalformedRoutingResponseException;
                }

                $cells[] = new MatrixCell(
                    sourceIndex: $sourceIndex,
                    targetIndex: $targetIndex,
                    distanceM: $distance === null ? null : (int) round((float) $distance * 1000),
                    durationS: $duration === null ? null : (int) round((float) $duration),
                );
            }
        }

        return new MatrixResult(
            cells: $cells,
            provider: $this->code(),
            routingEngineVersion: $this->engineVersion ?: $this->configuredEngineVersion(),
            osmDataVersion: $this->osmDataVersion(),
        );
    }

    private function post(string $path, array $payload): Response
    {
        $attempts = max(1, (int) config('logistics.valhalla.retry_times', 2) + 1);
        $delayMs = max(0, (int) config('logistics.valhalla.retry_delay_ms', 250));
        $lastException = null;
        $startedAt = microtime(true);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->pendingRequest()->post($this->url($path), $payload);

                if (! ($response->serverError() || $response->status() === 429) || $attempt === $attempts) {
                    Log::info('Valhalla routing request completed.', [
                        'action' => ltrim($path, '/'),
                        'status' => $response->status(),
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'attempt' => $attempt,
                    ]);

                    return $response;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt === $attempts) {
                    break;
                }
            }

            if ($delayMs > 0) {
                usleep($delayMs * (2 ** ($attempt - 1)) * 1000);
            }
        }

        Log::warning('Valhalla routing request unavailable.', [
            'action' => ltrim($path, '/'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'attempts' => $attempts,
        ]);

        throw new ProviderUnavailableException(previous: $lastException);
    }

    private function successfulJson(Response $response): array
    {
        $json = $response->json();

        if (! $response->successful()) {
            $this->throwProviderError($response, is_array($json) ? $json : []);
        }

        if (! is_array($json)) {
            throw new MalformedRoutingResponseException;
        }

        return $json;
    }

    private function throwProviderError(Response $response, array $json): never
    {
        $errorCode = (int) ($json['error_code'] ?? 0);
        $message = (string) ($json['error'] ?? $json['message'] ?? '');
        $normalized = mb_strtolower($message);

        if (in_array($errorCode, [170, 171, 441, 442], true)
            || str_contains($normalized, 'no path')
            || str_contains($normalized, 'unreachable')
            || str_contains($normalized, 'unconnected regions')) {
            throw new NoRouteException;
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new ProviderUnavailableException;
        }

        throw new RoutingException(
            message: $errorCode > 0
                ? "Routing-сервис отклонил запрос (код {$errorCode})."
                : 'Routing-сервис отклонил запрос.',
            domainCode: 'provider_rejected',
            retryable: false,
            httpStatus: 422,
        );
    }

    private function pendingRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('logistics.valhalla.connect_timeout', 3)))
            ->timeout(max(1, (int) config('logistics.valhalla.timeout', 30)));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('logistics.valhalla.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function configuredEngineVersion(): ?string
    {
        return config('logistics.valhalla.engine_version') ?: null;
    }

    private function osmDataVersion(): ?string
    {
        return $this->releaseMetadata?->osmDataVersion()
            ?? (config('logistics.osm_data_version') ?: null);
    }
}
