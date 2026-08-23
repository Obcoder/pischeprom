<?php

namespace App\Domain\AiSales\Policies;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;

final readonly class AiClassifiedField
{
    public function __construct(
        public string $subject,
        public string $field,
        public DataClassification $classification,
        public UnitVisibilityScope $visibilityScope,
        public array $allowedPurposes,
        public array $allowedAudiences,
        public bool $externalExportable,
        public string $redactionRule,
        public string $justification,
    ) {}
}
