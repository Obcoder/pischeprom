<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectingScoreSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_business_context_id' => $this->unit_business_context_id,
            'unit_product_match_id' => $this->getAttribute('unit_product_match_id'),
            'unit_good_match_id' => $this->getAttribute('unit_good_match_id'),
            'computed_score' => $this->computed_score,
            'effective_score' => $this->effective_score,
            'confidence' => $this->confidence,
            'band' => $this->band,
            'eligibility' => $this->eligibility,
            'review_status' => $this->review_status,
            'next_best_action' => $this->next_best_action,
            'definition' => [
                'code' => $this->definition_code,
                'version' => $this->definition_version,
                'hash' => $this->definition_hash,
            ],
            'input_hash' => $this->input_hash,
            'evidence_hash' => $this->evidence_hash,
            'origin' => $this->origin,
            'manual_override' => $this->origin === 'manual_override' ? [
                'base_snapshot_id' => $this->base_snapshot_id,
                'reason_code' => $this->override_reason_code,
                'safe_note' => $this->override_safe_note,
                'expires_at' => $this->override_expires_at?->toISOString(),
            ] : null,
            'stale' => $this->stale_at !== null,
            'stale_reason_code' => $this->stale_reason_code,
            'superseded' => $this->superseded_at !== null,
            'factors' => $this->whenLoaded('factors', fn () => $this->factors->map(fn ($factor): array => [
                'factor_code' => $factor->factor_code,
                'polarity' => $factor->polarity,
                'normalized_state' => $factor->normalized_state,
                'weight' => $factor->weight,
                'contribution' => $factor->contribution,
                'confidence' => $factor->confidence,
                'status' => $factor->status,
                'safe_rationale' => $factor->safe_rationale,
                'evidence' => $factor->evidence_reference ? [
                    'type' => $factor->evidence_type,
                    'reference' => $factor->evidence_reference,
                    'hash' => $factor->evidence_hash,
                    'at' => $factor->evidence_at?->toISOString(),
                ] : null,
            ])->values()),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'stale_at' => $this->stale_at?->toISOString(),
            'superseded_at' => $this->superseded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
