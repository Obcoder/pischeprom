<?php

namespace App\Services;

use App\Models\Purchase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
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

            return $purchase->load(['entity', 'goods']);
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $items = Arr::pull($data, 'items', []);

            $amount = $this->calculateAmount($items);

            $purchase->update([
                                  'date' => $data['date'],
                                  'entity_id' => $data['entity_id'],
                                  'amount' => $amount,
                              ]);

            $purchase->goods()->sync($this->prepareSyncData($items));

            return $purchase->load(['entity', 'goods']);
        });
    }

    protected function calculateAmount(array $items): float
    {
        return (float) collect($items)->sum(function ($item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            return $quantity * $price;
        });
    }

    protected function prepareSyncData(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            $goodId = (int) $item['good_id'];

            $syncData[$goodId] = [
                'quantity' => (float) ($item['quantity'] ?? 0),
                'measure_id' => !empty($item['measure_id']) ? (int) $item['measure_id'] : null,
                'price' => (float) ($item['price'] ?? 0),
                'currency_id' => !empty($item['currency_id']) ? (int) $item['currency_id'] : null,
            ];
        }

        return $syncData;
    }
}
