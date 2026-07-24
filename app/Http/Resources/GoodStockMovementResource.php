<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodStockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'good_id' => $this->good_id,
            'measure_id' => $this->measure_id,
            'type' => $this->type,
            'quantity_delta' => $this->quantity_delta,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'moved_at' => optional($this->moved_at)->toDateString(),
            'note' => $this->note,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'good' => $this->whenLoaded('good', fn () => [
                'id' => $this->good?->id,
                'name' => $this->good?->name,
                'slug' => $this->good?->slug,
                'ava_image' => $this->good?->ava_image,
                'ava_thumb' => $this->good?->ava_thumb,
                'is_published' => (bool) $this->good?->is_published,
            ]),
            'measure' => $this->whenLoaded('measure', fn () => [
                'id' => $this->measure?->id,
                'name' => $this->measure?->name,
            ]),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
