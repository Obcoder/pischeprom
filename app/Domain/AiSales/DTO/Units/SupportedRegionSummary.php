<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class SupportedRegionSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $country = null,
    ) {}

    public function fields(): array
    {
        return [
            'name' => self::text($this->name, 255),
            'country' => self::text($this->country, 255),
        ];
    }

    public function maxBytes(): int
    {
        return 2_048;
    }
}
