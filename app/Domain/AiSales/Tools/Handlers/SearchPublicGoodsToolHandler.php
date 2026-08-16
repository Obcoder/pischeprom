<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use App\Models\Good;

class SearchPublicGoodsToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $limit = min(20, max(1, (int) ($input['limit'] ?? 10)));
        $search = trim((string) $input['query']);
        $escaped = addcslashes($search, '%_\\');
        $direction = ($input['sort'] ?? 'name_asc') === 'name_desc' ? 'desc' : 'asc';
        $goods = Good::query()
            ->select(['id', 'name', 'description'])
            ->where('is_published', true)
            ->where('name', 'like', "%{$escaped}%")
            ->orderBy('name', $direction)
            ->orderBy('id', $direction)
            ->limit($limit)
            ->get();

        return new AiToolHandlerResult(
            $goods->map(static fn (Good $good): PublicGoodSummary => new PublicGoodSummary(
                $good->name,
                $good->description,
            ))->all(),
            'published_goods',
        );
    }
}
