<?php

namespace App\Domain\AiSales\DTO\Providers;

use App\Domain\AiSales\Enums\AiProviderErrorCategory;

final readonly class AiProviderError
{
    public function __construct(
        public AiProviderErrorCategory $category,
        public string $safeCode,
        public string $safeSummary,
        public bool $retryable = false,
    ) {}
}
