<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class ExtractionResult
{
    /** @param list<ExtractedRow> $rows */
    public function __construct(
        public array $rows,
        public string $parser,
        public bool $requiresOcr = false,
        public array $metadata = [],
        public array $warnings = [],
    ) {}
}
