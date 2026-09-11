<?php

namespace App\Services\Goods;

use App\Models\GoodStockMovement;
use App\Models\Purchase;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoodPurchaseStockSynchronizer
{
    public function __construct(private GoodStockMutationService $stockMutations) {}

    public function sync(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $purchase = Purchase::query()
                ->withoutEagerLoads()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $warehouseId = Warehouse::query()
                ->where('code', Warehouse::GOODS_CODE)
                ->value('id');

            if (! $warehouseId) {
                throw new RuntimeException('Системный склад goods не найден.');
            }

            $items = DB::table('good_purchase')
                ->where('purchase_id', $purchase->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'good_id', 'measure_id', 'quantity', 'price']);

            foreach ($items as $item) {
                $quantity = (float) $item->quantity;
                $price = (float) $item->price;
                if (
                    ! is_finite($quantity) || $quantity < 0.000001
                    || ! is_finite($price) || $price < 0
                    || ! is_finite($quantity * $price)
                ) {
                    throw ValidationException::withMessages([
                        'goods' => "Purchase #{$purchase->id}, позиция #{$item->id}: недопустимое количество или цена.",
                    ]);
                }
            }

            $goodIds = $items->pluck('good_id')
                ->merge($this->purchaseMovements($purchase)->pluck('good_id'))
                ->all();

            $this->stockMutations->run($goodIds, function () use ($purchase, $warehouseId, $items): void {
                $sourceIds = $items->pluck('id')->all();

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
            });
        }, 3);
    }

    public function remove(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $purchase = Purchase::query()
                ->withoutEagerLoads()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $goodIds = $this->purchaseMovements($purchase)->pluck('good_id')->all();

            $this->stockMutations->run(
                $goodIds,
                fn () => $this->deleteMovements($this->purchaseMovements($purchase))
            );
        }, 3);
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
            ->lockForUpdate()
            ->get()
            ->each
            ->delete();
    }
}
