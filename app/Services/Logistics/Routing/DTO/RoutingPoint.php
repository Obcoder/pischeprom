<?php

namespace App\Services\Logistics\Routing\DTO;

use InvalidArgumentException;

final readonly class RoutingPoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $name = null,
    ) {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Routing point coordinates are outside the valid range.');
        }
    }

    public function toValhalla(): array
    {
        return array_filter([
            'lat' => $this->latitude,
            'lon' => $this->longitude,
            'name' => $this->name,
            'type' => 'break',
        ], fn ($value) => $value !== null);
    }

    public function toArray(): array
    {
        return [
            'latitude' => round($this->latitude, 7),
            'longitude' => round($this->longitude, 7),
            'name' => $this->name,
        ];
    }
}
