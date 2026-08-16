<?php

namespace App\Domain\AiSales\Tools;

final readonly class AiToolDlpResult
{
    public function __construct(
        public string $decision,
        public int $findingCount,
        public int $redactionCount = 0,
    ) {}
}
