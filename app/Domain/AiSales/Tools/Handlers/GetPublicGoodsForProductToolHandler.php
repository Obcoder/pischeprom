<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use App\Models\Good;

class GetPublicGoodsForProductToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $limit = min(20, max(1, (int) ($input['limit'] ?? 10)));
        $direction = ($input['sort'] ?? 'name_asc') === 'name_desc' ? 'desc' : 'asc';
        $goods = Good::query()
            ->join('good_product', 'good_product.good_id', '=', 'goods.id')
            ->join('products', 'products.id', '=', 'good_product.product_id')
            ->where('products.id', (int) $input['product_id'])
            ->where('products.is_published', true)
            ->where('goods.is_published', true)
            ->select(['goods.id', 'goods.name', 'goods.description'])
            ->distinct()
            ->orderBy('goods.name', $direction)
            ->orderBy('goods.id', $direction)
            ->limit($limit)
            ->get();

        return new AiToolHandlerResult($goods->map(static fn (Good $good): PublicGoodSummary => new PublicGoodSummary(
            $good->name,
            $good->description,
        ))->all(), 'published_product_goods', (int) $input['product_id']);
    }
}
