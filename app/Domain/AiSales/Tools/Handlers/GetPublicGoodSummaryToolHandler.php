<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use App\Models\Good;

class GetPublicGoodSummaryToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $good = Good::query()
            ->select(['id', 'name', 'description'])
            ->where('is_published', true)
            ->whereKey((int) $input['good_id'])
            ->first();

        if (! $good) {
            throw new PolicyViolation('tool_subject_not_found', 'The requested published catalog item is unavailable.');
        }

        return new AiToolHandlerResult([
            new PublicGoodSummary($good->name, $good->description),
        ], 'published_goods', $good->id);
    }
}
