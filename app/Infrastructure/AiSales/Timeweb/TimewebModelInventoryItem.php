<?php

namespace App\Infrastructure\AiSales\Timeweb;

final readonly class TimewebModelInventoryItem
{
    public function __construct(
        public string $modelId,
        public string $displayLabel,
        public array $safeMetadata,
        public string $metadataHash,
    ) {}
}
