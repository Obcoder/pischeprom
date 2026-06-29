<?php

namespace App\Services\Gis\DTO;

class RouteResult
{
    private const SPEEDS_MPS = [
        'car' => 12.5,
        'truck' => 9.7,
        'pedestrian' => 1.4,
    ];

    public function __construct(
        public readonly string $provider,
        public readonly string $transportMode,
        public readonly ?int $distanceM,
        public readonly ?int $durationSec,
        public readonly array $geometry,
        public readonly array $points,
        public readonly array $summary = [],
    ) {}

    public static function localPreview(string $provider, array $points, string $transportMode, string $message): self
    {
        $distance = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            $distance += self::haversineMeters(
                (float) $points[$index - 1]['lat'],
                (float) $points[$index - 1]['lon'],
                (float) $points[$index]['lat'],
                (float) $points[$index]['lon'],
            );
        }

        $distanceM = (int) round($distance);
        $speed = self::SPEEDS_MPS[$transportMode] ?? self::SPEEDS_MPS['car'];
        $durationSec = $speed > 0 ? (int) ceil($distanceM / $speed) : null;

        return new self(
            provider: $provider,
            transportMode: $transportMode,
            distanceM: $distanceM,
            durationSec: $durationSec,
            geometry: [
                'type' => 'LineString',
                'coordinates' => array_map(
                    fn (array $point) => [(float) $point['lon'], (float) $point['lat']],
                    $points,
                ),
            ],
            points: $points,
            summary: [
                'provider' => $provider,
                'status' => 'stub',
                'message' => $message,
                'distance_strategy' => 'haversine_straight_segments',
            ],
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'transport_mode' => $this->transportMode,
            'distance_m' => $this->distanceM,
            'duration_sec' => $this->durationSec,
            'route_geometry_json' => $this->geometry,
            'points' => $this->points,
            'provider_response_summary' => $this->summary,
        ];
    }

    private static function haversineMeters(float $latFrom, float $lonFrom, float $latTo, float $lonTo): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($latTo - $latFrom);
        $lonDelta = deg2rad($lonTo - $lonFrom);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * sin($lonDelta / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}
