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
            'items_count' => ($this->items_count ?? 0) + ($this->service_items_count ?? 0),
            'commodity_items_count' => $this->whenCounted('items'),
            'service_items_count' => $this->whenCounted('serviceItems'),
            'commodity_items_total' => $this->relationLoaded('items')
                ? (float) $this->items->sum(fn ($item) => (float) $item->quantity * (float) $item->price)
                : null,
            'items' => CheckCommodityResource::collection($this->whenLoaded('items')),
            'service_items' => CheckServiceResource::collection($this->whenLoaded('serviceItems')),
            'commodities' => CommodityResource::collection($this->whenLoaded('commodities')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
