<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class UnitDuplicateCandidateSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $candidateReference,
        private readonly string $matchReason,
    ) {}

    public function fields(): array
    {
        return [
            'candidate_reference' => self::text($this->candidateReference, 64),
            'match_reason' => self::text($this->matchReason, 64),
        ];
    }

    public function maxBytes(): int
    {
        return 1_024;
    }
}
