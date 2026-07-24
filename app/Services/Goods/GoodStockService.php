<?php

namespace App\Services\Goods;

use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\GoodStockAvailability;
use Illuminate\Support\Facades\DB;

class GoodStockService
{
    private const EPSILON = 0.000001;

    public function isInStock(Good|int $good): bool
    {
        $goodId = $good instanceof Good ? $good->getKey() : $good;

        return DB::table('good_stock_movements')
            ->selectRaw('SUM(quantity_delta) as quantity')
            ->where('good_id', $goodId)
            ->groupBy('warehouse_id', 'measure_id')
            ->pluck('quantity')
            ->contains(fn ($quantity) => (float) $quantity > self::EPSILON);
    }

    public function availabilityStatus(Good $good): string
    {
        $state = $good->relationLoaded('stockAvailability')
            ? $good->stockAvailability
            : $good->stockAvailability()->first();

        if ($state) {
            return $state->is_in_stock ? 'in_stock' : 'out_of_stock';
        }

        if ($good->stockMovements()->exists()) {
            return $this->isInStock($good) ? 'in_stock' : 'out_of_stock';
        }

        return $good->seo?->availability_status ?: 'on_request';
    }

    public function ensureUnavailableState(Good $good): GoodStockAvailability
    {
        return GoodStockAvailability::query()->updateOrCreate(
            ['good_id' => $good->getKey()],
            [
                'is_in_stock' => false,
                'became_available_at' => null,
                'checked_at' => now(),
            ]
        );
    }

    public function syncAvailability(int $goodId): bool
    {
        if (! Good::query()->whereKey($goodId)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($goodId): bool {
            $state = GoodStockAvailability::query()
                ->where('good_id', $goodId)
                ->lockForUpdate()
                ->first();

            $isInStock = $this->isInStock($goodId);
            $wasInStock = $state?->is_in_stock ?? $isInStock;

            if ($state) {
                $state->update([
                    'is_in_stock' => $isInStock,
                    'became_available_at' => $isInStock
                        ? ($wasInStock ? $state->became_available_at : now())
                        : null,
                    'checked_at' => now(),
                ]);
            } else {
                GoodStockAvailability::query()->create([
                    'good_id' => $goodId,
                    'is_in_stock' => $isInStock,
                    'became_available_at' => $isInStock ? now() : null,
                    'checked_at' => now(),
                ]);
            }

            GoodSeo::query()->updateOrCreate(
                ['good_id' => $goodId],
                ['availability_status' => $isInStock ? 'in_stock' : 'out_of_stock']
            );

            return ! $wasInStock && $isInStock;
        }, 3);
    }
}
