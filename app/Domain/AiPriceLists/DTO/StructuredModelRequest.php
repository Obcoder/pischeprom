<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class StructuredModelRequest
{
    public function __construct(
        public string $instructions,
        public string $data,
        public array $schema,
        public string $schemaName,
        public string $promptVersion,
        public string $schemaVersion,
        public ?string $safetyIdentifier = null,
    ) {}
}
