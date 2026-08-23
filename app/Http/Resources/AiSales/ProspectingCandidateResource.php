<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectingCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $investigation = $this->resource->relationLoaded('searchResults')
            ? (new CandidateInvestigationResource($this->resource))->toArray($request)
            : null;

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
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($candidateProduct) => [
                'id' => $candidateProduct->product_id,
                'name' => $candidateProduct->product?->rus,
                'english_name' => $candidateProduct->product?->eng,
                'source' => $candidateProduct->source->value,
                'status' => $candidateProduct->status->value,
                'safe_rationale' => $candidateProduct->safe_rationale,
                'evidence_reference' => $candidateProduct->evidence_reference,
                'confidence' => $candidateProduct->confidence,
            ])->all()),
            'originating_goods' => $this->whenLoaded('job', fn () => $this->job?->relationLoaded('goods')
                ? $this->job->goods->map(fn ($good) => [
                    'id' => $good->id,
                    'name' => $good->name,
                    'compatibility_state' => $good->pivot->compatibility_state,
                ])->all() : []),
            'product_mapping_state' => $this->whenLoaded('job', fn () => $this->job?->product_mapping_state?->value),
            'channel_summary' => $this->whenLoaded('channels', fn () => $this->channels->groupBy(fn ($channel) => $channel->channel_kind->value)->map->count()->all()),
            'unit_matches' => $investigation === null ? [] : collect($investigation['duplicates'])->map(fn (array $match): array => [
                'unit' => $match['unit'],
                'signal_code' => $match['reason_code'],
                'strength' => $match['confidence'],
                'rank' => $match['rank'],
                'evidence_reference' => $match['evidence_reference'],
                'review_status' => $match['review_status'],
            ])->all(),
            'investigation' => $investigation,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'anonymized_at' => $this->anonymized_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
