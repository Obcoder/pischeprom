<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicProductSummary;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use App\Models\Product;

class SearchPublicProductsToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $limit = min(20, max(1, (int) ($input['limit'] ?? 10)));
        $escaped = addcslashes(trim((string) $input['query']), '%_\\');
        $direction = ($input['sort'] ?? 'name_asc') === 'name_desc' ? 'desc' : 'asc';
        $products = Product::query()->without(['category', 'manufacturers'])
            ->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'products.category_id')
                    ->where('categories.is_published', true);
            })
            ->where('products.is_published', true)
            ->where(function ($query) use ($escaped): void {
                $query->where('products.rus', 'like', "%{$escaped}%")
                    ->orWhere('products.eng', 'like', "%{$escaped}%");
            })
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->orderBy('products.rus', $direction)
            ->orderBy('products.id', $direction)
            ->limit($limit)
            ->get();

        return new AiToolHandlerResult($products->map(static fn ($product): PublicProductSummary => new PublicProductSummary(
            (int) $product->id,
            (string) $product->rus,
            $product->eng,
            $product->category_name,
        ))->all(), 'published_products');
    }
}
