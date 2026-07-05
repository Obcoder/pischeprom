<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'expense_article_id' => $this->expense_article_id,
            'project_id' => $this->project_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
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
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
