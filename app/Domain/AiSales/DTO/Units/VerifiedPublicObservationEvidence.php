<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class VerifiedPublicObservationEvidence extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $observationKey,
        private readonly string $summary,
        private readonly ?string $sourceLabel,
        private readonly ?string $sourceReference,
        private readonly ?string $observedAt,
    ) {}

    public function fields(): array
    {
        return [
            'observation_key' => self::text($this->observationKey, 128),
            'summary' => self::text($this->summary, 500),
            'source_label' => self::text($this->sourceLabel, 255),
            'source_reference' => self::text($this->sourceReference, 512),
            'observed_at' => self::text($this->observedAt, 40),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
