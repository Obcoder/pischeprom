<?php

namespace App\Domain\AiSales\DTO\Runs;

use App\Models\AiAgentRun;

final readonly class CreateAiAgentRunResult
{
    public function __construct(
        public AiAgentRun $run,
        public bool $created,
    ) {}
}
