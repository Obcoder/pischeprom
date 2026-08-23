<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class PublicProductSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly int $productId,
        private readonly string $name,
        private readonly ?string $englishName = null,
        private readonly ?string $category = null,
    ) {}

    public function fields(): array
    {
        return [
            'product_id' => max(1, $this->productId),
            'name' => self::text($this->name, 255),
            'english_name' => self::text($this->englishName, 255),
            'category' => self::text($this->category, 255),
        ];
    }

    public function maxBytes(): int
    {
        return 4_096;
    }
}
