<?php

namespace App\Domain\AiSales\DTO\Providers;

use App\Domain\AiSales\Enums\AiProviderResponseStatus;

final readonly class AiProviderResponse
{
    public function __construct(
        public AiProviderResponseStatus $status,
        public string $providerCode,
        public string $providerRoute,
        public string $modelId,
        public ?string $requestId,
        public array $outputItems,
        public array $toolCalls,
        public array $citations,
        public AiProviderUsage $usage,
        public ?AiProviderError $error = null,
    ) {}
}
