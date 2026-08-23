<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class SanitizedEntityLegalSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $legalName,
        private readonly ?string $entityType = null,
        private readonly ?string $country = null,
        private readonly ?string $registryIdentifierMasked = null,
    ) {}

    public function fields(): array
    {
        return [
            'legal_name' => self::text($this->legalName, 512),
            'entity_type' => self::text($this->entityType, 128),
            'country' => self::text($this->country, 128),
            'registry_identifier_masked' => self::text($this->registryIdentifierMasked, 64),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
