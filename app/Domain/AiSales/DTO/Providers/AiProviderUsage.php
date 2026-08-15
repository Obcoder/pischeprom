<?php

namespace App\Domain\AiSales\DTO\Providers;

final readonly class AiProviderUsage
{
    public function __construct(
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $reasoningTokens = null,
        public ?int $cachedTokens = null,
        public int $searchCount = 0,
        public int $toolCallCount = 0,
        public ?string $providerAmount = null,
        public ?string $providerCurrency = null,
        public string $normalizedRubAmount = '0.0000',
    ) {}

    public function totalTokens(): int
    {
        return max(0, (int) $this->inputTokens)
            + max(0, (int) $this->outputTokens)
            + max(0, (int) $this->reasoningTokens);
    }
}
