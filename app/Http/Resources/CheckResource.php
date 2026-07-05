<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => optional($this->date)->toDateString(),
            'entity_id' => $this->entity_id,
            'entity' => $this->whenLoaded('entity', fn () => [
                'id' => $this->entity?->id,
                'name' => $this->entity?->name,
                'classification' => $this->entity?->relationLoaded('classification') ? [
                    'id' => $this->entity?->classification?->id,
                    'name' => $this->entity?->classification?->name,
                ] : null,
            ]),
            'amount' => $this->amount,
            'items_count' => $this->whenCounted('items'),
            'items' => CheckCommodityResource::collection($this->whenLoaded('items')),
            'commodities' => CommodityResource::collection($this->whenLoaded('commodities')),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
