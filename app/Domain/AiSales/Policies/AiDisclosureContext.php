<?php

namespace App\Domain\AiSales\Policies;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;

final readonly class AiDisclosureContext
{
    public function __construct(
        public int $unitId,
        public int $unitBusinessContextId,
        public BusinessLane $lane,
        public UnitRoleCode $role,
        public AiAudience $audience,
        public AiPurpose $purpose,
        public bool $external,
    ) {}
}
