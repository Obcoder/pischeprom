<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectingSearchJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'purpose' => $this->purpose->value,
            'lane' => $this->lane->value,
            'default_role_code' => $this->default_role_code->value,
            'status' => $this->status->value,
            'safe_objective' => $this->safe_objective,
            'criteria' => $this->criteria_snapshot ?? [],
            'locale' => $this->locale,
            'limits' => [
                'max_queries' => $this->max_queries,
                'max_candidates' => $this->max_candidates,
                'max_results_per_query' => $this->max_results_per_query,
                'max_rows' => $this->max_rows,
                'max_bytes' => $this->max_bytes,
                'max_searches' => $this->max_searches,
                'max_cost_rub' => $this->max_cost_rub,
            ],
            'auto_create_unit' => false,
            'execution_available' => false,
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'primary_good' => $this->whenLoaded('primaryGood', fn () => $this->primaryGood ? ['id' => $this->primaryGood->id, 'name' => $this->primaryGood->name] : null),
            'goods' => $this->whenLoaded('goods', fn () => $this->goods->map(fn ($good) => [
                'id' => $good->id, 'name' => $good->name, 'role' => $good->pivot->role,
            ])->all()),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
