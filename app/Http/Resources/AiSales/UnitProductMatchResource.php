<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitProductMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_business_context_id' => $this->unit_business_context_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->rus,
                'english_name' => $this->product->eng,
            ]),
            'match_type' => $this->match_type->value,
            'status' => $this->status->value,
            'origin' => $this->origin->value,
            'evidence_confidence' => $this->evidence_confidence,
            'safe_rationale' => $this->safe_rationale,
            'evidence_reference' => $this->evidence_reference,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'stale_after' => $this->stale_after?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
