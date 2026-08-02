<?php

namespace App\Services\Logistics\Map;

use App\Services\Logistics\Routing\Support\Polyline6;
use InvalidArgumentException;

final class GeoJsonFactory
{
    /** @param list<array<string, mixed>> $features */
    public function featureCollection(array $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => array_values($features),
        ];
    }

    /** @param array<string, mixed> $properties */
    public function point(float $longitude, float $latitude, array $properties, int|string|null $id = null): array
    {
        if (! is_finite($latitude) || ! is_finite($longitude)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('GeoJSON point is outside the valid coordinate range.');
        }

        return array_filter([
            'type' => 'Feature',
            'id' => $id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [round($longitude, 7), round($latitude, 7)],
            ],
            'properties' => $properties,
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $properties */
    public function lineStringFromPolyline6(string $polyline, array $properties = [], int|string|null $id = null): array
    {
        $points = Polyline6::decode($polyline);
        if (count($points) < 2) {
            throw new InvalidArgumentException('A route GeoJSON LineString needs at least two points.');
        }

        $coordinates = array_map(function (array $point): array {
            [$latitude, $longitude] = $point;

            if (! is_finite($latitude) || ! is_finite($longitude)
                || $latitude < -90 || $latitude > 90
                || $longitude < -180 || $longitude > 180) {
                throw new InvalidArgumentException('Decoded route contains an invalid coordinate.');
            }

            return [round($longitude, 7), round($latitude, 7)];
        }, $points);

        return array_filter([
            'type' => 'Feature',
            'id' => $id,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates,
            ],
            'properties' => $properties,
        ], fn (mixed $value): bool => $value !== null);
    }
}
