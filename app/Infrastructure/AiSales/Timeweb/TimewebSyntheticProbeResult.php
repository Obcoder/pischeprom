<?php

namespace App\Infrastructure\AiSales\Timeweb;

final readonly class TimewebSyntheticProbeResult
{
    public function __construct(
        public string $route,
        public string $modelId,
        public array $capabilities,
        public array $budget,
        public bool $evidenceRecorded,
        public string $resultHash,
    ) {}
}
