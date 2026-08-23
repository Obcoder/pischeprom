<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\Queries\UnitSharedPublicProfileQuery;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;

class GetUnitSharedPublicProfileToolHandler implements AiToolHandlerInterface
{
    public function __construct(private readonly UnitSharedPublicProfileQuery $profiles) {}

    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        return new AiToolHandlerResult(
            [$this->profiles->get($context->unitId)],
            'unit_shared_public_profile',
            $context->unitId,
        );
    }
}
