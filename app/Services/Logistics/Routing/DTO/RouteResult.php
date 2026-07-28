<?php

namespace App\Services\Logistics\Routing\DTO;

final readonly class RouteResult
{
    public function __construct(
        public int $distanceM,
        public int $durationS,
        public ?string $shapePolyline6,
        public array $legs,
        public string $provider,
        public ?string $routingEngineVersion,
        public ?string $osmDataVersion,
    ) {}
}
