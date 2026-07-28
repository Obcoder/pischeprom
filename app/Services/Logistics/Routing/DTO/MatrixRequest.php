<?php

namespace App\Services\Logistics\Routing\DTO;

use InvalidArgumentException;

final readonly class MatrixRequest
{
    /** @param list<RoutingPoint> $sources @param list<RoutingPoint> $targets */
    public function __construct(
        public array $sources,
        public array $targets,
        public VehicleRoutingProfile $profile,
        public string $requestHash,
    ) {
        if ($sources === [] || $targets === []) {
            throw new InvalidArgumentException('A matrix needs at least one source and one target.');
        }
    }
}
