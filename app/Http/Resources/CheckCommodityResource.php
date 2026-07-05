<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckCommodityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'check_id' => $this->check_id,
            'commodity_id' => $this->commodity_id,
            'quantity' => $this->quantity,
            'measure_id' => $this->measure_id,
            'expense_article_id' => $this->expense_article_id,
            'price' => $this->price,
            'total_price' => $this->total_price,
            'measure' => $this->whenLoaded('measure', fn () => [
                'id' => $this->measure?->id,
                'name' => $this->measure?->name,
            ]),
            'expense_article' => $this->whenLoaded('expenseArticle', fn () => [
                'id' => $this->expenseArticle?->id,
                'name' => $this->expenseArticle?->name,
                'code' => $this->expenseArticle?->code,
                'color' => $this->expenseArticle?->color,
            ]),
            'commodity' => new CommodityResource($this->whenLoaded('commodity')),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
