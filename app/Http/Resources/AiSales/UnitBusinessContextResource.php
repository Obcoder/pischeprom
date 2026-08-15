<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitBusinessContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'lane' => $this->lane->value,
            'lane_label' => $this->lane->label(),
            'role_code' => $this->role_code->value,
            'role_label' => $this->marketRole?->display_name ?? $this->role_code->label(),
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'confidence' => $this->confidence,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'primary_good' => $this->whenLoaded('primaryGood', fn () => $this->primaryGood ? [
                'id' => $this->primaryGood->id,
                'name' => $this->primaryGood->name,
            ] : null),
            'primary_segment' => $this->primary_segment,
            'source' => $this->source,
            'first_activity_at' => $this->first_activity_at?->toISOString(),
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
