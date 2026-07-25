<?php

namespace App\Services\Goods;

use App\Models\GoodStockMovement;
use App\Models\Purchase;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoodPurchaseStockSynchronizer
{
    public function sync(Purchase $purchase): void
    {
        $warehouseId = Warehouse::query()
            ->where('code', Warehouse::GOODS_CODE)
            ->value('id');

        if (! $warehouseId) {
            throw new RuntimeException('Системный склад goods не найден.');
        }

        $items = DB::table('good_purchase')
            ->where('purchase_id', $purchase->id)
            ->orderBy('id')
            ->get([
                'id',
                'good_id',
                'measure_id',
                'quantity',
                'price',
            ]);

        $sourceIds = $items
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($items as $item) {
            GoodStockMovement::query()->updateOrCreate(
                [
                    'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
                    'source_id' => $item->id,
                ],
                [
                    'purchase_id' => $purchase->id,
                    'warehouse_id' => $warehouseId,
                    'good_id' => $item->good_id,
                    'measure_id' => $item->measure_id,
                    'type' => GoodStockMovement::TYPE_RECEIPT,
                    'quantity_delta' => abs((float) $item->quantity),
                    'unit_price' => (float) $item->price,
                    'moved_at' => optional($purchase->date)->toDateString()
                        ?: now()->toDateString(),
                    'note' => "Purchase #{$purchase->id}",
                ]
            );
        }

        $obsolete = $this->purchaseMovements($purchase)
            ->when(
                $sourceIds !== [],
                fn (Builder $query) => $query->whereNotIn('source_id', $sourceIds)
            );

        $this->deleteMovements($obsolete);
    }

    public function remove(Purchase $purchase): void
    {
        $this->deleteMovements($this->purchaseMovements($purchase));
    }

    private function purchaseMovements(Purchase $purchase): Builder
    {
        return GoodStockMovement::query()
            ->where('source_type', GoodStockMovement::SOURCE_GOOD_PURCHASE)
            ->where('purchase_id', $purchase->id);
    }

    private function deleteMovements(Builder $query): void
    {
        $query
            ->orderBy('id')
            ->get()
            ->each
            ->delete();
    }
}
