<?php

namespace App\Domain\AiSales\DTO\Routing;

final readonly class AiDlpFinding
{
    public function __construct(
        public string $detector,
        public string $ruleCode,
        public string $type,
        public string $action,
        public string $pathHash,
        public int $occurrences = 1,
    ) {}
}
