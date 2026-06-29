<?php

namespace App\Services\Gis\Providers;

use App\Services\Gis\Contracts\GisProviderInterface;
use App\Services\Gis\DTO\DistanceMatrixResult;
use App\Services\Gis\DTO\GeocodeResult;
use App\Services\Gis\DTO\ReverseGeocodeResult;
use App\Services\Gis\DTO\RouteResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class YandexProvider extends AbstractGisProvider implements GisProviderInterface
{
    public function code(): string
    {
        return 'yandex';
    }

    public function geocode(string $address, array $options = []): GeocodeResult
    {
        $apiKey = $this->requireApiKey(config('gis.providers.yandex.api_key'), 'Яндекс Карт');
        $startedAt = microtime(true);
        $response = null;

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get((string) config('gis.providers.yandex.geocode_url'), [
                    'apikey' => $apiKey,
                    'geocode' => $address,
                    'format' => 'json',
                    'lang' => 'ru_RU',
                    'results' => (int) ($options['limit'] ?? 5),
                ]);
        } catch (Throwable $throwable) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt, $throwable);
        }

        if (! $response->successful()) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt);
        }

        $items = collect(data_get($response->json(), 'response.GeoObjectCollection.featureMember', []))
            ->map(fn (array $item) => $this->mapGeoObject(data_get($item, 'GeoObject', [])))
            ->filter(fn (?array $item) => $item !== null)
            ->values()
            ->all();

        return new GeocodeResult(
            provider: $this->code(),
            address: $address,
            items: $items,
            summary: $this->requestSummary($this->code(), $response, $startedAt, [
                'items_count' => count($items),
            ]),
        );
    }

    public function reverseGeocode(float $lat, float $lon, array $options = []): ReverseGeocodeResult
    {
        $apiKey = $this->requireApiKey(config('gis.providers.yandex.api_key'), 'Яндекс Карт');
        $startedAt = microtime(true);
        $response = null;

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get((string) config('gis.providers.yandex.geocode_url'), [
                    'apikey' => $apiKey,
                    'geocode' => $lon.','.$lat,
                    'format' => 'json',
                    'lang' => 'ru_RU',
                    'results' => (int) ($options['limit'] ?? 5),
                ]);
        } catch (Throwable $throwable) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt, $throwable);
        }

        if (! $response->successful()) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt);
        }

        $items = collect(data_get($response->json(), 'response.GeoObjectCollection.featureMember', []))
            ->map(fn (array $item) => $this->mapGeoObject(data_get($item, 'GeoObject', [])))
            ->filter(fn (?array $item) => $item !== null)
            ->values()
            ->all();

        return new ReverseGeocodeResult(
            provider: $this->code(),
            lat: $lat,
            lon: $lon,
            address: $items[0]['address'] ?? null,
            items: $items,
            summary: $this->requestSummary($this->code(), $response, $startedAt, [
                'items_count' => count($items),
            ]),
        );
    }

    public function buildRoute(array $points, array $options = []): RouteResult
    {
        return RouteResult::localPreview(
            provider: $this->code(),
            points: $points,
            transportMode: (string) ($options['transport_mode'] ?? 'car'),
            message: 'Routing API Яндекс Карт пока не подключён в MVP; построена черновая линия по прямым отрезкам.',
        );
    }

    public function distanceMatrix(array $origins, array $destinations, array $options = []): DistanceMatrixResult
    {
        return DistanceMatrixResult::localEstimate(
            provider: $this->code(),
            origins: $origins,
            destinations: $destinations,
            transportMode: (string) ($options['transport_mode'] ?? 'car'),
            message: 'Distance Matrix API Яндекс Карт пока не подключён в MVP; рассчитана локальная матрица по прямым отрезкам.',
        );
    }

    private function mapGeoObject(array $geoObject): ?array
    {
        $position = trim((string) data_get($geoObject, 'Point.pos'));

        if ($position === '') {
            return null;
        }

        $coordinates = preg_split('/\s+/', $position) ?: [];

        if (count($coordinates) < 2 || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
            return null;
        }

        [$lon, $lat] = array_map('floatval', array_slice($coordinates, 0, 2));

        $metadata = data_get($geoObject, 'metaDataProperty.GeocoderMetaData', []);

        return [
            'provider' => $this->code(),
            'provider_object_id' => data_get($geoObject, 'uri'),
            'title' => data_get($geoObject, 'name'),
            'address' => data_get($metadata, 'text') ?: data_get($geoObject, 'description') ?: data_get($geoObject, 'name'),
            'lat' => $lat,
            'lon' => $lon,
            'precision_level' => $this->precisionLevel((string) data_get($metadata, 'precision')),
            'raw_type' => data_get($metadata, 'kind'),
        ];
    }

    private function precisionLevel(string $precision): string
    {
        return match ($precision) {
            'exact' => 'exact',
            'number' => 'building',
            'street' => 'street',
            'other', 'near' => 'unknown',
            default => $precision ?: 'unknown',
        };
    }
}
