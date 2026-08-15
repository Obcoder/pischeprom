<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitAliasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_business_context_id' => $this->unit_business_context_id,
            'alias' => $this->alias,
            'normalized_alias' => $this->normalized_alias,
            'alias_type' => $this->alias_type->value,
            'confidence' => $this->confidence,
            'verification_status' => $this->verification_status->value,
            'data_classification' => $this->data_classification->value,
            'visibility_scope' => $this->visibility_scope->value,
            'source' => $this->whenLoaded('source', fn () => $this->source ? [
                'id' => $this->source->id,
                'label' => $this->source->source_label,
                'reference' => $this->source->source_reference,
            ] : null),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
