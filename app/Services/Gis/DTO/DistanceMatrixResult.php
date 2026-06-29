<?php

namespace App\Services\Gis\DTO;

class DistanceMatrixResult
{
    private const SPEEDS_MPS = [
        'car' => 12.5,
        'truck' => 9.7,
        'pedestrian' => 1.4,
    ];

    public function __construct(
        public readonly string $provider,
        public readonly array $matrix = [],
        public readonly array $summary = [],
        public readonly array $origins = [],
        public readonly array $destinations = [],
    ) {}

    public static function notImplemented(string $provider): self
    {
        return new self($provider, [], [
            'status' => 'not_implemented',
            'message' => 'Матрица расстояний не входит в первый MVP GIS-модуля.',
        ]);
    }

    public static function localEstimate(
        string $provider,
        array $origins,
        array $destinations,
        string $transportMode,
        string $message,
    ): self {
        $speed = self::SPEEDS_MPS[$transportMode] ?? self::SPEEDS_MPS['car'];

        $matrix = collect($origins)
            ->values()
            ->map(function (array $origin, int $originIndex) use ($destinations, $speed) {
                return collect($destinations)
                    ->values()
                    ->map(function (array $destination, int $destinationIndex) use ($origin, $originIndex, $speed) {
                        $distanceM = (int) round(self::haversineMeters(
                            (float) $origin['lat'],
                            (float) $origin['lon'],
                            (float) $destination['lat'],
                            (float) $destination['lon'],
                        ));

                        return [
                            'origin_index' => $originIndex,
                            'destination_index' => $destinationIndex,
                            'distance_m' => $distanceM,
                            'duration_sec' => $speed > 0 ? (int) ceil($distanceM / $speed) : null,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values()
            ->all();

        return new self(
            provider: $provider,
            matrix: $matrix,
            summary: [
                'provider' => $provider,
                'status' => 'local_estimate',
                'message' => $message,
                'distance_strategy' => 'haversine_straight_segments',
                'transport_mode' => $transportMode,
            ],
            origins: $origins,
            destinations: $destinations,
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'origins' => $this->origins,
            'destinations' => $this->destinations,
            'matrix' => $this->matrix,
            'summary' => $this->summary,
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
