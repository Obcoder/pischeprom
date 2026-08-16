<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitGoodMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_business_context_id' => $this->unit_business_context_id,
            'unit_product_match_id' => $this->unit_product_match_id,
            'good' => $this->whenLoaded('good', fn () => ['id' => $this->good->id, 'name' => $this->good->name]),
            'offer_direction' => $this->match_type->value,
            'fit_score_available' => false,
            'evidence_confidence' => $this->confidence,
            'safe_rationale' => $this->safe_rationale,
            'evidence_reference' => $this->evidence_reference,
            'fit_status' => $this->fit_status?->value,
            'compatibility_state' => $this->compatibility_state->value,
            'origin' => $this->origin->value,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'stale_after' => $this->stale_after?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
