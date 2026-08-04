<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class OcrResponse
{
    public function __construct(
        public array $rows,
        public int $pages,
        public ?string $externalRequestId,
        public int $latencyMs,
        public array $metadata = [],
    ) {}
}
