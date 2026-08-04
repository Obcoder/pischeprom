<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class StructuredModelResponse
{
    public function __construct(
        public array $data,
        public string $model,
        public ?string $externalRequestId,
        public int $inputTokens,
        public int $outputTokens,
        public int $totalTokens,
        public int $latencyMs,
    ) {}
}
