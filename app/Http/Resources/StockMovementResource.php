<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'commodity_id' => $this->commodity_id,
            'measure_id' => $this->measure_id,
            'type' => $this->type,
            'quantity_delta' => $this->quantity_delta,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'moved_at' => optional($this->moved_at)->toDateString(),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'note' => $this->note,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'commodity' => new CommodityResource($this->whenLoaded('commodity')),
            'measure' => $this->whenLoaded('measure', fn () => [
                'id' => $this->measure?->id,
                'name' => $this->measure?->name,
            ]),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
