<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;

class SyntheticGoodToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        return new AiToolHandlerResult([
            new PublicGoodSummary(
                'Fictional starch blend',
                'Repository-owned synthetic catalog fixture.',
                ['sku' => 'SYN-001', 'category' => 'synthetic_ingredient'],
            ),
        ], 'repository_synthetic_fixture');
    }
}
