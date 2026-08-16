<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectingCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'job_id' => $this->whenLoaded('job', fn () => $this->job?->public_id),
            'purpose' => $this->purpose->value,
            'lane' => $this->lane->value,
            'role_code' => $this->role_code->value,
            'working_name' => $this->working_name,
            'domain' => $this->normalized_domain,
            'website' => $this->canonical_website,
            'location' => $this->location_display,
            'public_activity_summary' => $this->public_activity_summary,
            'relevance_summary' => $this->relevance_summary,
            'confidence_components' => $this->confidence_components ?? [],
            'source_count' => $this->source_count,
            'status' => $this->status->value,
            'resolution_outcome' => $this->resolution_outcome?->value,
            'resolution_reason_code' => $this->resolution_reason_code,
            'resolved_unit' => $this->whenLoaded('resolvedUnit', fn () => $this->resolvedUnit ? [
                'id' => $this->resolvedUnit->id, 'name' => $this->resolvedUnit->name,
            ] : null),
            'sources' => ProspectingCandidateSourceResource::collection($this->whenLoaded('sources')),
            'channel_summary' => $this->whenLoaded('channels', fn () => $this->channels->groupBy(fn ($channel) => $channel->channel_kind->value)->map->count()->all()),
            'unit_matches' => $this->whenLoaded('unitMatches', fn () => $this->unitMatches->map(fn ($match) => [
                'unit' => $match->unit ? ['id' => $match->unit->id, 'name' => $match->unit->name] : ['id' => $match->unit_id],
                'signal_code' => $match->signal_code,
                'strength' => $match->strength,
                'rank' => $match->rank,
                'evidence_reference' => $match->evidence_reference,
                'review_status' => $match->review_status,
            ])->all()),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'anonymized_at' => $this->anonymized_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
