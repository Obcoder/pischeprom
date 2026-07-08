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
            'items_count' => $this->goods_count ?? ($this->relationLoaded('goods') ? $this->goods->count() : 0),

            'entity' => $this->whenLoaded('entity', function () {
                return [
                    'id' => $this->entity->id,
                    'name' => $this->entity->name ?? null,
                    'full_name' => $this->entity->full_name ?? null,
                    'INN' => $this->entity->INN ?? null,
                    'KPP' => $this->entity->KPP ?? null,
                    'OGRN' => $this->entity->OGRN ?? null,
                    'units' => $this->entity->relationLoaded('units')
                        ? $this->entity->units->map(fn ($unit) => [
                            'id' => $unit->id,
                            'name' => $unit->name,
                            'is_customer' => (bool) $unit->is_customer,
                            'is_supplier' => (bool) $unit->is_supplier,
                        ])->values()
                        : [],
                ];
            }),

            'items' => $this->whenLoaded('goods', function () {
                return $this->goods->map(function ($good) {
                    return [
                        'pivot_id' => $good->pivot->id ?? null,
                        'good_id' => $good->id,
                        'good_name' => $good->name ?? null,
                        'good' => [
                            'id' => $good->id,
                            'name' => $good->name ?? null,
                            'slug' => $good->slug ?? null,
                            'ava_image' => $good->ava_image ?? null,
                            'ava_thumb' => $good->ava_thumb ?? null,
                            'thumbnail_url' => $good->ava_thumb ?: $good->ava_image,
                        ],
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
