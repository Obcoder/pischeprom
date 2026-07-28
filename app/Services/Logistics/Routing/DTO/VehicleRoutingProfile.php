<?php

namespace App\Services\Logistics\Routing\DTO;

final readonly class VehicleRoutingProfile
{
    public function __construct(
        public string $costing,
        public array $options,
        public string $hash,
    ) {}

    public function toArray(): array
    {
        return [
            'costing' => $this->costing,
            'options' => $this->options,
            'hash' => $this->hash,
        ];
    }
}
