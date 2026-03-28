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
            'goods' => $this->whenLoaded('goods', function () {
                return $this->goods->map(function ($good) {
                    return [
                        'id' => $good->id,
                        'name' => $good->name ?? null,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
