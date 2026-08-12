<?php

namespace App\Http\Resources;

use App\Http\Resources\Logistics\TripExpenseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $commodityItemsTotal = $this->relationLoaded('items')
            ? (float) $this->items->sum(fn ($item) => (float) $item->quantity * (float) $item->price)
            : null;
        $serviceItemsTotal = $this->relationLoaded('serviceItems')
            ? (float) $this->serviceItems->sum(fn ($item) => (float) $item->quantity * (float) $item->price)
            : null;

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
                'units' => $this->entity?->relationLoaded('units')
                    ? $this->entity->units->map(fn ($unit) => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                    ])->values()
                    : [],
            ]),
            'amount' => $this->amount,
            'items_count' => ($this->items_count ?? 0) + ($this->service_items_count ?? 0),
            'commodity_items_count' => $this->whenCounted('items'),
            'service_items_count' => $this->whenCounted('serviceItems'),
            'commodity_items_total' => $commodityItemsTotal,
            'service_items_total' => $serviceItemsTotal,
            'positions_total' => $commodityItemsTotal !== null && $serviceItemsTotal !== null
                ? $commodityItemsTotal + $serviceItemsTotal
                : null,
            'table_summary' => $this->resource->getAttribute('table_summary') ?? [
                'expense_articles' => [],
                'projects' => [],
            ],
            'items' => CheckCommodityResource::collection($this->whenLoaded('items')),
            'service_items' => CheckServiceResource::collection($this->whenLoaded('serviceItems')),
            'commodities' => CommodityResource::collection($this->whenLoaded('commodities')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'logistics_expenses' => TripExpenseResource::collection($this->whenLoaded('logisticsExpenses')),
            'logistics_trips' => $this->whenLoaded('logisticsExpenses', fn () => $this->logisticsExpenses
                ->map(fn ($expense) => $expense->trip)
                ->filter()
                ->unique('id')
                ->values()
                ->map(fn ($trip) => [
                    'id' => $trip->id,
                    'number' => $trip->number,
                    'status' => $trip->status?->value,
                ])),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
