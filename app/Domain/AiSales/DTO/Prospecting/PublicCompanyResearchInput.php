<?php

namespace App\Domain\AiSales\DTO\Prospecting;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class PublicCompanyResearchInput extends AbstractSafeAiDto
{
    private readonly array $data;

    public function __construct(
        string $domain,
        ?string $pageTitle,
        ?string $metaDescription,
        array $headings,
        string $visibleText,
        array $productNames,
        ?string $geography,
    ) {
        $this->data = [
            'domain' => self::text($domain, 253),
            'page_title' => self::text($pageTitle, 512),
            'meta_description' => self::text($metaDescription, 1000),
            'headings' => self::stringList($headings, 20, 300),
            'visible_text' => self::text($visibleText, 20_000),
            'product_names' => self::stringList($productNames, 25, 255),
            'geography' => self::text($geography, 255),
            'trust_level' => 'untrusted',
            'instruction_authority' => 'none',
        ];
    }

    public function fields(): array
    {
        return $this->data;
    }

    public function maxBytes(): int
    {
        return 32_768;
    }
}
