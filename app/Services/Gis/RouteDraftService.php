<?php

namespace App\Services\Gis;

use App\Models\Entity;
use App\Models\GisRouteDraft;
use App\Services\Gis\DTO\DistanceMatrixResult;
use App\Services\Gis\DTO\RouteResult;
use App\Services\Gis\Exceptions\GisValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RouteDraftService
{
    public function __construct(
        private readonly GisService $gis,
        private readonly EntityLocationService $locations,
    ) {}

    public function preview(array $payload): RouteResult
    {
        $provider = $this->gis->normalizeProvider($payload['provider'] ?? null);
        $transportMode = $this->gis->normalizeTransportMode($payload['transport_mode'] ?? 'car');
        $points = $this->normalizePointList($payload['points'] ?? [], 2, 'маршрута');

        return $this->gis->provider($provider)->buildRoute($points, [
            'transport_mode' => $transportMode,
        ]);
    }

    public function distanceMatrix(array $payload): DistanceMatrixResult
    {
        $provider = $this->gis->normalizeProvider($payload['provider'] ?? null);
        $transportMode = $this->gis->normalizeTransportMode($payload['transport_mode'] ?? 'car');
        $origins = $this->normalizePointList($payload['origins'] ?? [], 1, 'origins');
        $destinations = $this->normalizePointList($payload['destinations'] ?? [], 1, 'destinations');

        return $this->gis->provider($provider)->distanceMatrix($origins, $destinations, [
            'transport_mode' => $transportMode,
        ]);
    }

    public function storeDraft(array $payload, ?int $createdBy = null): GisRouteDraft
    {
        $route = $this->preview($payload);

        return DB::transaction(function () use ($payload, $route, $createdBy) {
            $draft = GisRouteDraft::query()->create([
                'name' => $payload['name'] ?? null,
                'provider' => $route->provider,
                'transport_mode' => $route->transportMode,
                'distance_m' => $route->distanceM,
                'duration_sec' => $route->durationSec,
                'route_geometry_json' => $route->geometry,
                'provider_response_summary' => $route->summary,
                'created_by' => $createdBy,
            ]);

            foreach ($route->points as $point) {
                $draft->points()->create([
                    'sequence_no' => $point['sequence_no'],
                    'entity_id' => $point['entity_id'] ?? null,
                    'title' => $point['title'] ?? null,
                    'address_text' => $point['address_text'] ?? null,
                    'lat' => $point['lat'],
                    'lon' => $point['lon'],
                    'point_type' => $point['point_type'] ?? 'manual',
                ]);
            }

            $this->syncDraftGeoPoints($draft->id);

            return $draft->load('points.entity');
        });
    }

    public function payload(GisRouteDraft $draft): array
    {
        $draft->loadMissing('points.entity', 'creator');

        return [
            'id' => $draft->id,
            'name' => $draft->name,
            'provider' => $draft->provider,
            'transport_mode' => $draft->transport_mode,
            'distance_m' => $draft->distance_m,
            'duration_sec' => $draft->duration_sec,
            'route_geometry_json' => $draft->route_geometry_json,
            'provider_response_summary' => $draft->provider_response_summary,
            'created_by' => $draft->created_by,
            'creator' => $draft->creator?->id ? [
                'id' => $draft->creator->id,
                'name' => $draft->creator->name,
            ] : null,
            'points' => $draft->points->map(fn ($point) => [
                'id' => $point->id,
                'sequence_no' => $point->sequence_no,
                'entity_id' => $point->entity_id,
                'entity_name' => $point->entity?->id ? $point->entity->name : null,
                'title' => $point->title,
                'address_text' => $point->address_text,
                'lat' => $point->lat,
                'lon' => $point->lon,
                'point_type' => $point->point_type,
                'created_at' => $point->created_at?->toISOString(),
            ])->values(),
            'created_at' => $draft->created_at?->toISOString(),
            'updated_at' => $draft->updated_at?->toISOString(),
        ];
    }

    public function normalizePoints(array $points): array
    {
        return $this->normalizePointList($points, 2, 'маршрута');
    }

    private function normalizePoint(array $point, int $sequenceNo): array
    {
        if (filled($point['entity_id'] ?? null)) {
            $entity = Entity::query()
                ->with(['location', 'buildings.city', 'cities', 'classification'])
                ->find((int) $point['entity_id']);

            if (! $entity) {
                throw new GisValidationException('Entity #'.$point['entity_id'].' не найдена.');
            }

            if (! $entity->location || $entity->location->lat === null || $entity->location->lon === null) {
                throw new GisValidationException('У entity #'.$entity->id.' нет координат.');
            }

            return [
                'sequence_no' => $sequenceNo,
                'entity_id' => $entity->id,
                'title' => $point['title'] ?? $entity->name,
                'address_text' => $point['address_text'] ?? $entity->location->address_text ?? $this->locations->resolveEntityAddress($entity),
                'lat' => (float) $entity->location->lat,
                'lon' => (float) $entity->location->lon,
                'point_type' => 'entity',
            ];
        }

        [$lat, $lon] = $this->gis->assertCoordinates($point['lat'] ?? null, $point['lon'] ?? null);

        return [
            'sequence_no' => $sequenceNo,
            'entity_id' => null,
            'title' => filled($point['title'] ?? null) ? trim((string) $point['title']) : 'Точка '.$sequenceNo,
            'address_text' => filled($point['address_text'] ?? null) ? trim((string) $point['address_text']) : null,
            'lat' => $lat,
            'lon' => $lon,
            'point_type' => $this->normalizePointType($point['point_type'] ?? 'manual'),
        ];
    }

    private function normalizePointType(mixed $type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, ['entity', 'manual', 'city', 'warehouse'], true)
            ? $type
            : 'manual';
    }

    private function normalizePointList(array $points, int $min, string $label): array
    {
        $points = array_values($points);

        if (count($points) < $min) {
            throw new GisValidationException($min === 2
                ? 'Для построения маршрута нужно минимум две точки.'
                : 'Для матрицы расстояний нужен минимум один элемент в '.$label.'.');
        }

        return collect($points)
            ->map(fn (array $point, int $index) => $this->normalizePoint($point, $index + 1))
            ->values()
            ->all();
    }

    private function syncDraftGeoPoints(int $draftId): void
    {
        if (! Schema::hasColumn('gis_route_points', 'geo_point')) {
            return;
        }

        DB::table('gis_route_points')
            ->where('route_draft_id', $draftId)
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->update([
                'geo_point' => DB::raw("ST_GeomFromText(CONCAT('POINT(', lon, ' ', lat, ')'), 4326)"),
            ]);
    }
}
