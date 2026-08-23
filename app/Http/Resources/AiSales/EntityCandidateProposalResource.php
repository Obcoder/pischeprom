<?php

namespace App\Http\Resources\AiSales;

use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityCandidateProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewIdentities = app(UnitContextAuthorizationService::class)->hasPermission(
            $request->user(),
            UnitContextAuthorizationService::VIEW_CLASSIFICATIONS,
        );

        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_business_context_id' => $this->unit_business_context_id,
            'action' => $this->action->value,
            'existing_entity' => $this->when($canViewIdentities && $this->relationLoaded('existingEntity'), fn () => $this->existingEntity ? [
                'id' => $this->existingEntity->id,
                'name' => $this->existingEntity->name,
            ] : null),
            'proposed_name' => $this->proposed_name,
            'evidence_summary' => $this->evidence_summary,
            'duplicate_candidate_ids' => $canViewIdentities ? ($this->duplicate_candidate_ids ?? []) : [],
            'status' => $this->status->value,
            'review_required' => true,
            'entity_was_changed' => false,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
