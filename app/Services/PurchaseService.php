<?php

namespace App\Services;

use App\Models\Purchase;
use App\Services\Goods\GoodPurchaseStockSynchronizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected GoodPurchaseStockSynchronizer $stockSynchronizer
    ) {}

    public function store(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $items = Arr::pull($data, 'items', []);

            $amount = $this->calculateAmount($items);

            $purchase = Purchase::create([
                'date' => $data['date'],
                'entity_id' => $data['entity_id'],
                'amount' => $amount,
            ]);

            $purchase->goods()->sync($this->prepareSyncData($items));
            $this->stockSynchronizer->sync($purchase);

            return $purchase->load(['entity', 'goods']);
        }, 3);
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::query()
                ->withoutEagerLoads()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = Arr::pull($data, 'items', []);

            $amount = $this->calculateAmount($items);

            $purchase->update([
                'date' => $data['date'],
                'entity_id' => $data['entity_id'],
                'amount' => $amount,
            ]);

            $purchase->goods()->sync($this->prepareSyncData($items));
            $this->stockSynchronizer->sync($purchase);

            return $purchase->load(['entity', 'goods']);
        }, 3);
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $purchase = Purchase::query()
                ->withoutEagerLoads()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->stockSynchronizer->remove($purchase);
            $purchase->delete();
        }, 3);
    }

    protected function calculateAmount(array $items): float
    {
        $goodIds = array_map(fn (array $item) => (int) $item['good_id'], $items);
        if (count($goodIds) !== count(array_unique($goodIds))) {
            throw ValidationException::withMessages([
                'items' => 'Каждый товар можно указать в закупке только один раз.',
            ]);
        }

        $amount = 0.0;
        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            if (
                ! is_finite($quantity) || $quantity < 0.000001
                || ! is_finite($price) || $price < 0
                || ! is_finite($quantity * $price)
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Количество должно быть положительным, цена и стоимость — допустимыми неотрицательными числами.',
                ]);
            }

            $amount += $quantity * $price;
        }

        if (! is_finite($amount)) {
            throw ValidationException::withMessages([
                'items' => 'Стоимость закупки выходит за допустимые пределы.',
            ]);
        }

        return $amount;
    }

    protected function prepareSyncData(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            $goodId = (int) $item['good_id'];

            $syncData[$goodId] = [
                'quantity' => (float) ($item['quantity'] ?? 0),
                'measure_id' => ! empty($item['measure_id']) ? (int) $item['measure_id'] : null,
                'price' => (float) ($item['price'] ?? 0),
                'currency_id' => ! empty($item['currency_id']) ? (int) $item['currency_id'] : null,
            ];
        }

        return $syncData;
    }
}
