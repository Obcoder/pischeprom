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
            $goods = Arr::pull($data, 'goods', []);

            $purchase = Purchase::create([
                                             'date' => $data['date'],
                                             'entity_id' => $data['entity_id'],
                                             'amount' => $data['amount'] ?? 0,
                                         ]);

            $purchase->goods()->sync(
                collect($goods)->pluck('id')->unique()->values()->all()
            );

            return $purchase->load(['entity', 'goods']);
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $goods = Arr::pull($data, 'goods', []);

            $purchase->update([
                                  'date' => $data['date'],
                                  'entity_id' => $data['entity_id'],
                                  'amount' => $data['amount'] ?? 0,
                              ]);

            $purchase->goods()->sync(
                collect($goods)->pluck('id')->unique()->values()->all()
            );

            return $purchase->load(['entity', 'goods']);
        });
    }
}
