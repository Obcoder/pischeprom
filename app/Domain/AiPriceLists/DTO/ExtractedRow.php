<?php

namespace App\Domain\AiPriceLists\DTO;

readonly class ExtractedRow
{
    public function __construct(
        public int $position,
        public array $cells,
        public string $text,
        public ?string $sheet = null,
        public ?int $page = null,
        public ?int $table = null,
        public ?int $row = null,
        public ?string $range = null,
        public array $evidence = [],
    ) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->sheet,
            $this->page,
            $this->table,
            $this->row,
            $this->range,
            $this->cells,
            $this->text,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
