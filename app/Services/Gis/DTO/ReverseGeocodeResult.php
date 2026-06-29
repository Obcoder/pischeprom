<?php

namespace App\Services\Gis\DTO;

class ReverseGeocodeResult
{
    public function __construct(
        public readonly string $provider,
        public readonly float $lat,
        public readonly float $lon,
        public readonly ?string $address,
        public readonly array $items = [],
        public readonly array $summary = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'address' => $this->address,
            'items' => $this->items,
            'summary' => $this->summary,
        ];
    }
}
