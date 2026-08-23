<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class AggregateSupplySummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $productName,
        private readonly string $region,
        private readonly string $capacityBand,
        private readonly int $supplierCount,
        private readonly string $evidencePeriod,
    ) {}

    public function fields(): array
    {
        return [
            'product_name' => self::text($this->productName, 255),
            'region' => self::text($this->region, 255),
            'capacity_band' => self::text($this->capacityBand, 96),
            'supplier_count' => max(0, $this->supplierCount),
            'evidence_period' => self::text($this->evidencePeriod, 64),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
