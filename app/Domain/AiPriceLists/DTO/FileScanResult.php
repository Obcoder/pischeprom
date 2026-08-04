<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class FileScanResult
{
    public function __construct(
        public bool $clean,
        public string $scanner,
        public ?string $reason = null,
    ) {}
}
