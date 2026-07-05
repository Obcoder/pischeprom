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
            'expense_article_id' => $this->expense_article_id,
            'project_id' => $this->project_id,

            'expense_article' => $this->whenLoaded('expenseArticle', fn () => [
                'id' => $this->expenseArticle?->id,
                'name' => $this->expenseArticle?->name,
                'code' => $this->expenseArticle?->code,
                'color' => $this->expenseArticle?->color,
            ]),

            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project?->id,
                'name' => $this->project?->name,
                'code' => $this->project?->code,
            ]),

            'checks_count' => $this->whenCounted('checks'),
            'media_count' => $this->whenCounted('media'),
            'stock_movements_count' => $this->whenCounted('stockMovements'),

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
