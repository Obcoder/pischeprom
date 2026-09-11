<?php

namespace App\Services\Goods;

use App\Models\GoodStockMovement;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodSaleStockSynchronizer
{
    public function __construct(private GoodStockMutationService $mutations) {}

    /**
     * A saved Sale is posted: payment_status concerns payments, not stock.
     * allowNegativeStock is reserved for reconciliation of historical documents.
     */
    public function sync(Sale $sale, bool $allowNegativeStock = false): void
    {
        DB::transaction(function () use ($sale, $allowNegativeStock): void {
            $sale = Sale::query()->without('entity')->whereKey($sale->id)
                ->lockForUpdate()->firstOrFail();
            $items = DB::table('good_sale')->where('sale_id', $sale->id)
                ->orderBy('id')->lockForUpdate()->get();
            $previousGoodIds = GoodStockMovement::query()
                ->where('source_type', GoodStockMovement::SOURCE_GOOD_SALE)
                ->where('sale_id', $sale->id)->pluck('good_id');

            if ($items->isEmpty() && $previousGoodIds->isEmpty()) {
                return;
            }

            $warehouseId = Warehouse::query()->where('code', Warehouse::GOODS_CODE)
                ->where('is_active', true)->value('id');
            if (! $warehouseId) {
                throw ValidationException::withMessages([
                    'goods' => 'Активный системный склад goods не найден. Продажа не проведена.',
                ]);
            }

            foreach ($items as $item) {
                if (! is_finite((float) $item->quantity) || (float) $item->quantity < 0.000001) {
                    throw ValidationException::withMessages([
                        'goods' => "Sale #{$sale->id}, позиция #{$item->id}: количество должно быть положительным (не менее 0.000001).",
                    ]);
                }
                if (! $item->measure_id) {
                    throw ValidationException::withMessages([
                        'goods' => "Sale #{$sale->id}, позиция #{$item->id}: не указана единица измерения.",
                    ]);
                }
            }

            $goodIds = $items->pluck('good_id')->merge($previousGoodIds)->unique()->all();
            $this->mutations->run($goodIds, function () use ($sale, $items, $warehouseId): void {
                $existing = GoodStockMovement::query()
                    ->where('source_type', GoodStockMovement::SOURCE_GOOD_SALE)
                    ->where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('source_id');

                // Current reads after locking the goods also work at MySQL REPEATABLE READ.
                $stock = DB::table('good_stock_movements')->where('warehouse_id', $warehouseId)
                    ->whereIn('good_id', $items->pluck('good_id'))
                    ->orderBy('id')->lockForUpdate()->get();

                foreach ($items as $item) {
                    $previous = $existing->get($item->id);
                    $sameStock = $previous
                        && (int) $previous->warehouse_id === (int) $warehouseId
                        && (int) $previous->good_id === (int) $item->good_id
                        && (int) $previous->measure_id === (int) $item->measure_id;
                    $cost = $this->validateCost($sameStock ? $previous->unit_price : $this->unitCost($stock, $item));
                    $this->validateCost($cost * round((float) $item->quantity, 6));

                    GoodStockMovement::query()->updateOrCreate([
                        'source_type' => GoodStockMovement::SOURCE_GOOD_SALE,
                        'source_id' => $item->id,
                    ], [
                        'sale_id' => $sale->id,
                        'purchase_id' => null,
                        'warehouse_id' => $warehouseId,
                        'good_id' => $item->good_id,
                        'measure_id' => $item->measure_id,
                        'type' => GoodStockMovement::TYPE_WRITE_OFF,
                        'quantity_delta' => -round((float) $item->quantity, 6),
                        'unit_price' => $cost,
                        'moved_at' => $sale->date->toDateString(),
                        'note' => "Sale #{$sale->id}",
                    ]);
                }

                $sourceIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
                $existing->filter(fn (GoodStockMovement $movement) => ! in_array((int) $movement->source_id, $sourceIds, true))
                    ->each->delete();
            }, $allowNegativeStock);
        }, 3);
    }

    private function unitCost(Collection $stock, object $item): float
    {
        $bucket = $stock->filter(fn ($row) => (int) $row->good_id === (int) $item->good_id
            && $row->measure_id !== null && (int) $row->measure_id === (int) $item->measure_id);
        $quantity = (float) $bucket->sum('quantity_delta');
        $value = (float) $bucket->sum(fn ($row) => $row->quantity_delta * $row->unit_price);

        if (! is_finite($quantity) || ! is_finite($value)) {
            throw ValidationException::withMessages(['goods' => 'Складская стоимость выходит за допустимые пределы. Выполните сверку движений товара.']);
        }

        if ($quantity > 0.000000001 && $value >= 0) {
            return $value / $quantity;
        }

        // Historical sales may precede stock accounting. Estimate cost from recorded receipts,
        // never from the selling price, and leave the shortage visible for reconciliation.
        $receipts = $bucket->filter(fn ($row) => $row->quantity_delta > 0);
        $received = (float) $receipts->sum('quantity_delta');

        return $received > 0
            ? max(0, (float) $receipts->sum(fn ($row) => $row->quantity_delta * $row->unit_price) / $received)
            : 0;
    }

    private function validateCost(float $cost): float
    {
        if (! is_finite($cost) || $cost < 0) {
            throw ValidationException::withMessages(['goods' => 'Себестоимость товара должна быть конечным неотрицательным числом.']);
        }

        return $cost;
    }
}
