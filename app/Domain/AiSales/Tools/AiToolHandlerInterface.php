<?php

namespace App\Domain\AiSales\Tools;

interface AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult;
}
