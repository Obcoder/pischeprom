<?php

namespace App\Services\Checks;

use App\Models\Check;
use App\Models\CheckCommodity;
use App\Models\Commodity;
use App\Models\StockMovement;
use App\Models\Warehouse;

class CheckCommodityService
{
    public function create(
        Check $check,
        array $data,
        bool $withRelations = true
    ): CheckCommodity {
        $commodity = Commodity::findOrFail($data['commodity_id']);

        $item = CheckCommodity::create([
            'check_id' => $check->id,
            'commodity_id' => $commodity->id,
            'warehouse_id' => $this->resolveWarehouseId($data['warehouse_id'] ?? null),
            'quantity' => $data['quantity'] ?? 1,
            'measure_id' => $data['measure_id'] ?? null,
            'expense_article_id' => $data['expense_article_id'] ?? $commodity->expense_article_id,
            'price' => $data['price'] ?? 0,
        ]);

        $this->syncStockMovement($item);

        return $withRelations ? $item->fresh($this->relations()) : $item;
    }

    public function update(CheckCommodity $item, array $data): CheckCommodity
    {
        if (array_key_exists('warehouse_id', $data)) {
            $data['warehouse_id'] = $this->resolveWarehouseId($data['warehouse_id']);
        }

        if (array_key_exists('commodity_id', $data) && ! array_key_exists('expense_article_id', $data)) {
            $commodity = Commodity::findOrFail($data['commodity_id']);
            $data['expense_article_id'] = $commodity->expense_article_id;
        }

        $item->update($data);
        $this->syncStockMovement($item->fresh(['check']));

        return $item->fresh($this->relations());
    }

    public function delete(CheckCommodity $item): void
    {
        StockMovement::query()
            ->where('source_type', StockMovement::SOURCE_CHECK_COMMODITY)
            ->where('source_id', $item->id)
            ->delete();

        $item->delete();
    }

    private function resolveWarehouseId(mixed $warehouseId = null): int
    {
        if ($warehouseId) {
            return (int) $warehouseId;
        }

        $warehouse = Warehouse::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($warehouse) {
            return $warehouse->id;
        }

        $warehouse = Warehouse::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($warehouse) {
            return $warehouse->id;
        }

        return Warehouse::create([
            'name' => 'Основной склад',
            'code' => 'main',
            'is_active' => true,
            'sort_order' => 100,
        ])->id;
    }

    private function syncStockMovement(CheckCommodity $item): void
    {
        $item->loadMissing('check');

        StockMovement::query()->updateOrCreate(
            [
                'source_type' => StockMovement::SOURCE_CHECK_COMMODITY,
                'source_id' => $item->id,
            ],
            [
                'warehouse_id' => $this->resolveWarehouseId($item->warehouse_id),
                'commodity_id' => $item->commodity_id,
                'measure_id' => $item->measure_id,
                'type' => StockMovement::TYPE_CHECK_PURCHASE,
                'quantity_delta' => $item->quantity,
                'unit_price' => $item->price,
                'moved_at' => optional($item->check?->date)->toDateString() ?: now()->toDateString(),
                'note' => "Check #{$item->check_id}",
            ]
        );
    }

    private function relations(): array
    {
        return [
            'commodity.avaMedia',
            'commodity.expenseArticle',
            'commodity.project',
            'expenseArticle',
            'measure',
            'warehouse',
        ];
    }
}
