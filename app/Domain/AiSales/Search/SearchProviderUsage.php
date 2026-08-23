<?php

namespace App\Domain\AiSales\Search;

final readonly class SearchProviderUsage
{
    public function __construct(
        public int $requestCount,
        public int $resultCount,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public string $estimatedCostRub = '0.0000',
    ) {}
}
