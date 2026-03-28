<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'amount' => $this->amount,

            'entity' => $this->whenLoaded('entity', function () {
                return [
                    'id' => $this->entity->id,
                    'name' => $this->entity->name ?? null,
                ];
            }),

            'items' => $this->whenLoaded('goods', function () {
                return $this->goods->map(function ($good) {
                    return [
                        'pivot_id' => $good->pivot->id ?? null,
                        'good_id' => $good->id,
                        'good_name' => $good->name ?? null,
                        'quantity' => (float) ($good->pivot->quantity ?? 0),
                        'measure_id' => $good->pivot->measure_id,
                        'price' => (float) ($good->pivot->price ?? 0),
                        'currency_id' => $good->pivot->currency_id,
                        'total' => (float) ($good->pivot->total ?? 0),
                    ];
                })->values();
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
