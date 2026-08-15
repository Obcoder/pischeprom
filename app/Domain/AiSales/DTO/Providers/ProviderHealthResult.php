<?php

namespace App\Domain\AiSales\DTO\Providers;

final readonly class ProviderHealthResult
{
    public function __construct(
        public bool $available,
        public string $status,
        public string $safeSummary,
        public string $checkedAt,
    ) {}
}
