<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->rus,
            'english_name' => $this->eng,
            'category' => $this->category_name,
        ];
    }
}
