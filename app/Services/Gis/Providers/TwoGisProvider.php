<?php

namespace App\Services\Gis\Providers;

use App\Services\Gis\Contracts\GisProviderInterface;
use App\Services\Gis\DTO\DistanceMatrixResult;
use App\Services\Gis\DTO\GeocodeResult;
use App\Services\Gis\DTO\ReverseGeocodeResult;
use App\Services\Gis\DTO\RouteResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwoGisProvider extends AbstractGisProvider implements GisProviderInterface
{
    public function code(): string
    {
        return '2gis';
    }

    public function geocode(string $address, array $options = []): GeocodeResult
    {
        $apiKey = $this->requireApiKey(config('gis.providers.2gis.api_key'), '2ГИС');
        $startedAt = microtime(true);
        $response = null;

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get((string) config('gis.providers.2gis.geocode_url'), [
                    'q' => $address,
                    'key' => $apiKey,
                    'fields' => 'items.point,items.address,items.full_address_name,items.name,items.id,items.type,items.subtype',
                    'page_size' => (int) ($options['limit'] ?? 5),
                ]);
        } catch (Throwable $throwable) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt, $throwable);
        }

        if (! $response->successful()) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt);
        }

        $items = collect(data_get($response->json(), 'result.items', []))
            ->map(fn (array $item) => $this->mapGeocodeItem($item))
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
        $apiKey = $this->requireApiKey(config('gis.providers.2gis.api_key'), '2ГИС');
        $startedAt = microtime(true);
        $response = null;

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get((string) config('gis.providers.2gis.geocode_url'), [
                    'lat' => $lat,
                    'lon' => $lon,
                    'key' => $apiKey,
                    'fields' => 'items.point,items.address,items.full_address_name,items.name,items.id,items.type,items.subtype',
                    'page_size' => (int) ($options['limit'] ?? 5),
                ]);
        } catch (Throwable $throwable) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt, $throwable);
        }

        if (! $response->successful()) {
            throw $this->providerRequestFailed($this->code(), $response, $startedAt);
        }

        $items = collect(data_get($response->json(), 'result.items', []))
            ->map(fn (array $item) => $this->mapGeocodeItem($item))
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
            message: 'Routing API 2ГИС пока не подключён в MVP; построена черновая линия по прямым отрезкам.',
        );
    }

    public function distanceMatrix(array $origins, array $destinations, array $options = []): DistanceMatrixResult
    {
        return DistanceMatrixResult::localEstimate(
            provider: $this->code(),
            origins: $origins,
            destinations: $destinations,
            transportMode: (string) ($options['transport_mode'] ?? 'car'),
            message: 'Distance Matrix API 2ГИС пока не подключён в MVP; рассчитана локальная матрица по прямым отрезкам.',
        );
    }

    private function mapGeocodeItem(array $item): ?array
    {
        $lat = data_get($item, 'point.lat');
        $lon = data_get($item, 'point.lon');

        if ($lat === null || $lon === null) {
            return null;
        }

        return [
            'provider' => $this->code(),
            'provider_object_id' => data_get($item, 'id'),
            'title' => data_get($item, 'name') ?: data_get($item, 'full_name'),
            'address' => data_get($item, 'full_address_name')
                ?: data_get($item, 'address_name')
                ?: data_get($item, 'address.comment')
                ?: data_get($item, 'name'),
            'lat' => (float) $lat,
            'lon' => (float) $lon,
            'precision_level' => $this->precisionLevel($item),
            'raw_type' => data_get($item, 'type'),
        ];
    }

    private function precisionLevel(array $item): string
    {
        $type = (string) data_get($item, 'type');
        $subtype = (string) data_get($item, 'subtype');

        if (str_contains($type, 'building') || str_contains($subtype, 'building')) {
            return 'building';
        }

        if (str_contains($type, 'street') || str_contains($subtype, 'street')) {
            return 'street';
        }

        if (str_contains($type, 'adm_div') || str_contains($subtype, 'city')) {
            return 'city';
        }

        return data_get($item, 'id') ? 'exact' : 'unknown';
    }
}
