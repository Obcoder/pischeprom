<?php

namespace App\Domain\AiSales\Search;

final readonly class SearchProviderResult
{
    public function __construct(
        public int $rank,
        public ?string $title,
        public string $url,
        public ?string $domain,
        public ?string $snippet,
        public string $resultType = 'organic',
    ) {}
}
