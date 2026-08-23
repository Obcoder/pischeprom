<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class PublicGoodSummary extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $description = null,
        private readonly array $publishedAttributes = [],
    ) {}

    public function fields(): array
    {
        return [
            'name' => self::text($this->name, 255),
            'description' => self::text($this->description, 1200),
            'published_attributes' => self::scalarMap($this->publishedAttributes, 20, 255),
        ];
    }

    public function maxBytes(): int
    {
        return 8_192;
    }
}
