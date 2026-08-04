<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class FileValidationResult
{
    public function __construct(
        public bool $valid,
        public bool $quarantined,
        public bool $unsupported,
        public string $extension,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public bool $requiresOcr,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}
}
