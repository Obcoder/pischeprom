<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class UnitBusinessContextSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $contextReference,
        private readonly string $lane,
        private readonly string $roleCode,
        private readonly string $stage,
        private readonly string $status,
        private readonly ?string $ownerLabel = null,
        private readonly ?string $reviewerLabel = null,
        private readonly ?string $primaryGoodName = null,
        private readonly ?string $lastActivityAt = null,
    ) {}

    public function fields(): array
    {
        return [
            'context_reference' => self::text($this->contextReference, 96),
            'lane' => self::text($this->lane, 32),
            'role_code' => self::text($this->roleCode, 32),
            'stage' => self::text($this->stage, 48),
            'status' => self::text($this->status, 24),
            'owner_label' => self::text($this->ownerLabel, 255),
            'reviewer_label' => self::text($this->reviewerLabel, 255),
            'primary_good_name' => self::text($this->primaryGoodName, 255),
            'last_activity_at' => self::text($this->lastActivityAt, 40),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
