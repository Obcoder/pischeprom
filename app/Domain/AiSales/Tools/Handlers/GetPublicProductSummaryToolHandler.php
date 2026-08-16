<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicProductSummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use App\Models\Product;

class GetPublicProductSummaryToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $product = Product::query()->without(['category', 'manufacturers'])
            ->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'products.category_id')
                    ->where('categories.is_published', true);
            })
            ->where('products.is_published', true)
            ->where('products.id', (int) $input['product_id'])
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->first();

        if (! $product) {
            throw new PolicyViolation('tool_subject_not_found', 'The requested published Product is unavailable.');
        }

        return new AiToolHandlerResult([
            new PublicProductSummary((int) $product->id, (string) $product->rus, $product->eng, $product->category_name),
        ], 'published_products', (int) $product->id);
    }
}
