<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_business_context_id' => $this->unit_business_context_id,
            'observation_key' => $this->observation_key,
            'normalized_value' => $this->normalized_value,
            'summary' => $this->summary,
            'source_reference' => $this->source_reference,
            'source' => $this->whenLoaded('source', fn () => $this->source ? [
                'id' => $this->source->id,
                'type' => $this->source->source_type,
                'label' => $this->source->source_label,
                'reference' => $this->source->source_reference,
                'url' => $this->source->source_url,
            ] : null),
            'verification_status' => $this->verification_status->value,
            'confidence' => $this->confidence,
            'data_classification' => $this->data_classification->value,
            'visibility_scope' => $this->visibility_scope->value,
            'observed_at' => $this->observed_at?->toISOString(),
            'last_checked_at' => $this->last_checked_at?->toISOString(),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
