<?php

namespace App\Domain\AiSales\Web;

final readonly class PublicPageExtract
{
    /**
     * @param  list<string>  $headings
     * @param  list<string>  $sameDomainLinks
     * @param  list<array{kind: string, value: string, contact_role: string}>  $channels
     */
    public function __construct(
        public ?string $title,
        public ?string $metaDescription,
        public array $headings,
        public string $visibleText,
        public array $sameDomainLinks,
        public array $channels,
        public string $contentHash,
    ) {}
}
