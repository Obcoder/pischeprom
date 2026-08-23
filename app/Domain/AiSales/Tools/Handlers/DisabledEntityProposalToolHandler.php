<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;

class DisabledEntityProposalToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        throw new PolicyViolation(
            'tool_human_review_required',
            'Entity proposal tooling is metadata-only and cannot execute on Stage 07.',
        );
    }
}
