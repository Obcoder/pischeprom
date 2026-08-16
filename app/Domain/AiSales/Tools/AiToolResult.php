<?php

namespace App\Domain\AiSales\Tools;

final readonly class AiToolResult
{
    public function __construct(
        public string $status,
        public array $items,
        public string $outputHash,
        public int $rowCount,
        public int $byteCount,
        public int $queryCount,
        public int $redactionCount,
        public int $durationMs,
        public bool $replayed = false,
    ) {}
}
