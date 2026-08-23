<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindBuyersProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
