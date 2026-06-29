<?php

namespace App\Services\Gis\DTO;

class GeocodeResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $address,
        public readonly array $items,
        public readonly array $summary = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'address' => $this->address,
            'items' => $this->items,
            'summary' => $this->summary,
        ];
    }
}
