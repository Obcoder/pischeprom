<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommodityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ava' => $this->ava,
            'ava_url' => $this->ava_url,

            'checks_count' => $this->whenCounted('checks'),
            'media_count' => $this->whenCounted('media'),

            'media' => CommodityMediaResource::collection(
                $this->whenLoaded('media')
            ),

            'ava_media' => new CommodityMediaResource(
                $this->whenLoaded('avaMedia')
            ),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
