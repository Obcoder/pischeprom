<?php

namespace App\Services\Logistics\Routing\DTO;

use InvalidArgumentException;

final readonly class RouteRequest
{
    /** @param list<RoutingPoint> $points */
    public function __construct(
        public array $points,
        public VehicleRoutingProfile $profile,
        public string $requestHash,
    ) {
        if (count($points) < 2) {
            throw new InvalidArgumentException('A route needs at least two points.');
        }
    }
}
