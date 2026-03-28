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

            $amount = collect($items)->sum(fn ($item) => (float) $item['total']);

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

            $amount = collect($items)->sum(fn ($item) => (float) $item['total']);

            $purchase->update([
                                  'date' => $data['date'],
                                  'entity_id' => $data['entity_id'],
                                  'amount' => $amount,
                              ]);

            $purchase->goods()->sync($this->prepareSyncData($items));

            return $purchase->load(['entity', 'goods']);
        });
    }

    protected function prepareSyncData(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            $goodId = (int) $item['good_id'];

            $syncData[$goodId] = [
                'quantity' => (float) $item['quantity'],
                'measure_id' => $item['measure_id'] ?: null,
                'price' => (float) $item['price'],
                'currency_id' => $item['currency_id'] ?: null,
                'total' => (float) $item['total'],
            ];
        }

        return $syncData;
    }
}
