<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class PublicBusinessContactSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $channelType,
        private readonly string $value,
        private readonly ?string $sourceLabel,
        private readonly bool $verified,
    ) {}

    public function fields(): array
    {
        return [
            'channel_type' => self::text($this->channelType, 16),
            'value' => self::text($this->value, 512),
            'source_label' => self::text($this->sourceLabel, 255),
            'verified' => $this->verified,
        ];
    }

    public function maxBytes(): int
    {
        return 2_048;
    }
}
