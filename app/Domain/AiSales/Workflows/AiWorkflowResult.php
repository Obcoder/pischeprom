<?php

namespace App\Domain\AiSales\Workflows;

final readonly class AiWorkflowResult
{
    public function __construct(
        public string $status,
        public string $workflowCode,
        public string $workflowVersion,
        public string $workflowHash,
        public int $toolCallCount,
        public int $rowCount,
        public int $byteCount,
        public int $durationMs,
        public ?string $safeErrorCode = null,
        public bool $replayed = false,
    ) {}
}
