<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class OcrRequest
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $fileName,
    ) {}
}
