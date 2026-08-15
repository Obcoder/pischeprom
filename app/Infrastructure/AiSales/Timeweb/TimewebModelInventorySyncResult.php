<?php

namespace App\Infrastructure\AiSales\Timeweb;

final readonly class TimewebModelInventorySyncResult
{
    public function __construct(
        public string $route,
        public bool $applied,
        public int $discovered,
        public int $created,
        public int $updated,
        public int $markedInactive,
        public array $modelIds,
        public ?string $requestId,
        public string $resultHash,
        public array $budget,
    ) {}
}
