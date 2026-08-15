<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class AggregateDemandSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $productName,
        private readonly string $period,
        private readonly string $quantityBand,
        private readonly int $regionCount,
        private readonly int $sampleSize,
    ) {}

    public function fields(): array
    {
        return [
            'product_name' => self::text($this->productName, 255),
            'period' => self::text($this->period, 64),
            'quantity_band' => self::text($this->quantityBand, 96),
            'region_count' => max(0, $this->regionCount),
            'sample_size' => max(0, $this->sampleSize),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
