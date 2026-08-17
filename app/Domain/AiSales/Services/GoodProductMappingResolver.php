<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProductMappingState;
use Illuminate\Support\Facades\DB;

class GoodProductMappingResolver
{
    /** @return list<int> */
    public function distinctProductIds(int $goodId, ?int $limit = null): array
    {
        $query = DB::table('good_product')
            ->where('good_id', $goodId)
            ->distinct()
            ->orderBy('product_id');
        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query
            ->pluck('product_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @param null|list<int> $selectedProductIds */
    public function state(int $goodId, ?array $selectedProductIds = null): ProductMappingState
    {
        $productIds = $this->distinctProductIds($goodId);

        if ($productIds === []) {
            return ProductMappingState::MissingProductMapping;
        }

        if (count($productIds) !== 1) {
            return ProductMappingState::AmbiguousProductMapping;
        }

        if ($selectedProductIds !== null && ! in_array($productIds[0], array_map('intval', $selectedProductIds), true)) {
            return ProductMappingState::ProductScopeMismatch;
        }

        return ProductMappingState::Mapped;
    }

    public function exactProductId(int $goodId): ?int
    {
        $productIds = $this->distinctProductIds($goodId);

        return count($productIds) === 1 ? $productIds[0] : null;
    }
}
