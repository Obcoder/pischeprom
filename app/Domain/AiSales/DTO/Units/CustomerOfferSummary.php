<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class CustomerOfferSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $goodName,
        private readonly string $price,
        private readonly string $currency,
        private readonly ?string $measure = null,
        private readonly ?string $validUntil = null,
    ) {}

    public function fields(): array
    {
        return [
            'good_name' => self::text($this->goodName, 255),
            'price' => self::text($this->price, 64),
            'currency' => self::text($this->currency, 8),
            'measure' => self::text($this->measure, 64),
            'valid_until' => self::text($this->validUntil, 32),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
