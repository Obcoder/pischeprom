<?php

namespace App\Domain\AiSales\Workflows;

use App\Domain\AiSales\Enums\ProductScopeRole;
use Illuminate\Support\Facades\DB;

final class PublicResearchProductScope
{
    /** @return list<string> */
    public function namesForJob(int $jobId): array
    {
        return DB::table('prospecting_search_job_products as scope')
            ->join('products', 'products.id', '=', 'scope.product_id')
            ->where('scope.prospecting_search_job_id', $jobId)
            ->whereIn('scope.role', [ProductScopeRole::Primary->value, ProductScopeRole::Additional->value])
            ->where('products.is_published', true)
            ->select(['products.id', 'products.rus', 'products.eng'])
            ->distinct()
            ->orderBy('products.id')
            ->get()
            ->flatMap(fn ($product) => array_filter([$product->rus, $product->eng]))
            ->unique()
            ->take(25)
            ->values()
            ->all();
    }
}
