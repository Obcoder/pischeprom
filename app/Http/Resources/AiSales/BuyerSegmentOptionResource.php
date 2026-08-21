<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BuyerSegmentOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'source' => $this->resource['source'],
            'type' => $this->resource['type'],
            'recommended' => (bool) $this->resource['recommended'],
        ];
    }
}
