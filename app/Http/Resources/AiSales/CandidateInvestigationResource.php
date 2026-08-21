<?php

namespace App\Http\Resources\AiSales;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitAliasType;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Prospecting\ResultBusinessRoleClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CandidateInvestigationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sources = $this->sourceItems();
        $classification = $this->buyerClassification();

        return [
            'candidate' => [
                'public_id' => $this->public_id,
                'status' => $this->status->value,
                'resolution_outcome' => $this->resolution_outcome?->value,
                'suggested_working_name' => $this->working_name,
                'working_name_origin' => $this->workingNameOrigin(),
                'location' => [
                    'display' => $this->location_display,
                    'city' => $this->city?->name,
                    'region' => $this->city?->region?->name ?? $this->region?->name,
                ],
                'lane' => $this->lane->value,
                'role' => $this->role_code->value,
            ],
            'identity' => [
                'registrable_domain' => $this->normalized_domain,
                'public_site_name' => $this->normalized_domain,
                'canonical_site_url' => $this->safeUrl($this->canonical_website, $this->normalized_domain),
                'inferred_company_name' => $this->resolvedUnit?->name,
                'confidence' => $this->boundedConfidence('identity'),
                'verification_status' => $this->resolvedUnit ? 'human_resolved' : 'unresolved',
                'evidence_status' => $sources->isEmpty() ? 'missing' : 'public_source_present',
                'rationale' => $this->resolvedUnit
                    ? 'human_reviewed_unit_identity'
                    : ($this->normalized_domain ? 'public_site_observed_company_identity_unverified' : 'company_identity_evidence_missing'),
                'requires_human_name_confirmation' => $this->resolvedUnit === null,
            ],
            'buyer_classification' => $classification,
            'product_scope' => $this->productScopeItems(),
            'sources' => $sources->values()->all(),
            'facts' => $this->factItems(),
            'public_contacts' => $this->publicContactItems(),
            'duplicates' => $this->duplicateItems($request),
        ];
    }

    private function buyerClassification(): array
    {
        $results = $this->loadedCollection('searchResults');
        if ($results->isEmpty()) {
            return [
                'role' => 'unknown', 'reason_codes' => ['search_evidence_missing'],
                'confidence' => 0, 'research_eligible' => false, 'candidate_eligible' => false,
            ];
        }
        $classifier = app(ResultBusinessRoleClassifier::class);
        $combined = $results->map(fn ($result): string => implode(' ', array_filter([
            $result->title, $result->snippet, $result->publicFetch?->page_title,
            $result->publicFetch?->meta_description, $result->publicFetch?->text_excerpt,
            ...((array) ($result->publicFetch?->headings ?? [])),
            $result->research?->safe_summary,
            ...((array) ($result->research?->activity_mentions ?? [])),
        ])))->implode(' ');

        return $classifier->classifyEvidence($combined, (string) $this->normalized_domain, $this->lane)->safeArray();
    }

    private function sourceItems(): Collection
    {
        $results = $this->loadedCollection('searchResults')->keyBy('result_hash');

        return $this->loadedCollection('sources')->map(function ($source) use ($results): array {
            $resultHash = str_starts_with((string) $source->source_reference, 'search-result:')
                ? substr((string) $source->source_reference, strlen('search-result:'))
                : null;
            $result = $resultHash ? $results->get($resultHash) : null;
            $fetch = $result?->publicFetch;
            $research = $result?->research;
            $observedAt = $research?->completed_at ?? $fetch?->fetched_at ?? $source->accessed_at ?? $source->published_at;

            return [
                'id' => $source->id,
                'reference' => $source->source_reference,
                'title' => $source->title,
                'safe_url' => $this->safeUrl($source->canonical_url, $source->source_domain),
                'registrable_domain' => $result?->registrable_domain ?? $source->source_domain,
                'source_kind' => $source->source_type,
                'fetch_status' => $fetch?->status ?? $result?->fetch_status ?? 'not_recorded',
                'research_status' => $research?->status ?? $result?->research_status ?? 'not_recorded',
                'freshness' => [
                    'observed_at' => $observedAt?->toISOString(),
                    'published_at' => $source->published_at?->toISOString(),
                    'age_days' => $observedAt ? max(0, (int) $observedAt->diffInDays(now())) : null,
                    'status' => $observedAt ? 'recorded' : 'unknown',
                ],
                'evidence_reference' => $source->source_reference,
                'evidence_hash' => $source->evidence_hash,
                'safe_excerpt' => $source->bounded_excerpt,
                'safe_claim_summary' => $research?->safe_summary,
                'confidence' => $source->confidence,
                'source_quality' => $source->source_quality,
            ];
        });
    }

    private function productScopeItems(): array
    {
        $researchMentions = $this->loadedCollection('searchResults')
            ->pluck('research')->filter()
            ->flatMap(fn ($research) => $research->product_mentions ?? [])
            ->filter(fn ($mention) => is_string($mention) && trim($mention) !== '')
            ->map(fn (string $mention): string => mb_substr(trim($mention), 0, 255))
            ->unique()->take(25)->values();

        return $this->loadedCollection('products')->map(function ($candidateProduct) use ($researchMentions): array {
            $score = $this->latestProductScore($candidateProduct);
            $statements = collect([$candidateProduct->safe_rationale])
                ->merge($researchMentions)
                ->filter(fn ($statement) => is_string($statement) && trim($statement) !== '')
                ->map(fn (string $statement): string => mb_substr(trim($statement), 0, 1000))
                ->unique()->take(25)->values()->all();

            return [
                'product' => [
                    'id' => $candidateProduct->product_id,
                    'name' => $candidateProduct->product?->rus,
                    'english_name' => $candidateProduct->product?->eng,
                ],
                'match_status' => $candidateProduct->status->value,
                'source' => $candidateProduct->source->value,
                'rationale' => $candidateProduct->safe_rationale,
                'evidence_statements' => $statements,
                'evidence_reference' => $candidateProduct->evidence_reference,
                'evidence_hash' => $candidateProduct->evidence_hash,
                'confidence' => $candidateProduct->confidence,
                'score_status' => $score ? 'calculated' : 'not_calculated',
                'score' => $score,
            ];
        })->values()->all();
    }

    private function latestProductScore($candidateProduct): ?array
    {
        if ($this->resolved_unit_id === null || ! $candidateProduct->relationLoaded('unitProductMatches')) {
            return null;
        }

        $lane = $this->lane->value;
        $snapshot = $candidateProduct->unitProductMatches
            ->filter(fn ($match) => $match->businessContext?->lane?->value === $lane
                && (int) $match->unit_id === (int) $this->resolved_unit_id)
            ->flatMap(fn ($match) => $match->relationLoaded('relevanceSnapshots') ? $match->relevanceSnapshots : collect())
            ->filter(fn ($score) => $score->superseded_at === null)
            ->sortByDesc('id')->first();

        return $snapshot ? [
            'computed_score' => $snapshot->computed_score,
            'effective_score' => $snapshot->effective_score,
            'confidence' => $snapshot->confidence,
            'eligibility' => $snapshot->eligibility,
            'review_status' => $snapshot->review_status,
            'calculated_at' => $snapshot->created_at?->toISOString(),
        ] : null;
    }

    private function factItems(): array
    {
        $references = $this->loadedCollection('sources')->pluck('evidence_hash')->filter()->values()->all();
        $facts = collect();

        if (filled($this->public_activity_summary)) {
            $facts->push($this->fact(
                'activity_summary',
                (string) $this->public_activity_summary,
                $this->boundedConfidence('relevance'),
                $references,
                'human_review_required',
            ));
        }
        if (filled($this->location_display)) {
            $facts->push($this->fact('geography', (string) $this->location_display, null, $references, 'human_review_required'));
        }

        foreach ($this->loadedCollection('searchResults') as $result) {
            $research = $result->research;
            if (! $research || $research->status !== 'completed' || ! $research->schema_valid) {
                continue;
            }
            $source = $this->loadedCollection('sources')->first(
                fn ($item) => $item->source_reference === 'search-result:'.$result->result_hash,
            );
            $evidence = array_values(array_filter([$source?->evidence_hash, $research->output_hash]));
            foreach (['activity_mentions' => 'activity', 'location_hints' => 'geography', 'product_mentions' => 'product_mention'] as $field => $type) {
                foreach (array_slice((array) ($research->{$field} ?? []), 0, 25) as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        $facts->push($this->fact($type, $value, null, $evidence, 'research_extracted_unverified'));
                    }
                }
            }
        }

        return $facts->unique(fn (array $fact): string => hash('sha256', $fact['type'].'|'.$fact['summary'].'|'.implode('|', $fact['evidence_references'])))
            ->take(50)->values()->all();
    }

    private function fact(string $type, string $summary, ?int $confidence, array $references, string $status): array
    {
        return [
            'type' => $type,
            'summary' => mb_substr(trim($summary), 0, 1000),
            'confidence' => $confidence,
            'evidence_references' => array_slice(array_values(array_unique($references)), 0, 20),
            'verification_status' => $status,
        ];
    }

    private function publicContactItems(): array
    {
        return $this->loadedCollection('channels')
            ->filter(fn ($channel) => $channel->contact_role === 'business_general'
                && $channel->verification_status === ObservationVerificationStatus::Verified
                && $channel->data_classification === DataClassification::Public)
            ->map(fn ($channel): array => [
                'kind' => $channel->channel_kind->value,
                'display' => $channel->masked_display,
                'confidence' => $channel->confidence,
                'verification_status' => $channel->verification_status->value,
                'communication_state' => $channel->communication_state->value,
                'last_verified_at' => $channel->last_verified_at?->toISOString(),
                'evidence_reference' => $channel->source?->source_reference,
                'evidence_hash' => $channel->source?->evidence_hash,
            ])->take(20)->values()->all();
    }

    private function duplicateItems(Request $request): array
    {
        $lane = $this->lane->value;
        $laneScope = $lane === 'sales' ? UnitVisibilityScope::SalesLane : UnitVisibilityScope::ProcurementLane;

        return $this->loadedCollection('unitMatches')->filter(function ($match) use ($request, $lane): bool {
            return $match->unit
                && Gate::forUser($request->user())->allows('view', $match->unit)
                && $match->unit->relationLoaded('businessContexts')
                && $match->unit->businessContexts->contains(fn ($context) => $context->lane->value === $lane);
        })->map(function ($match) use ($lane, $laneScope): array {
            $unit = $match->unit;
            $domainAlias = $unit->relationLoaded('aliases') ? $unit->aliases
                ->filter(fn ($alias) => $alias->alias_type === UnitAliasType::DomainName
                    && $alias->data_classification === DataClassification::Public
                    && in_array($alias->visibility_scope, [UnitVisibilityScope::SharedPublic, $laneScope], true)
                    && ($alias->businessContext === null || $alias->businessContext->lane->value === $lane))
                ->sortByDesc(fn ($alias) => $alias->verification_status === ObservationVerificationStatus::Verified)
                ->first() : null;
            $city = $unit->relationLoaded('cities') ? $unit->cities->first() : null;

            return [
                'unit' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'city' => $city?->name,
                    'region' => $city?->region?->name,
                    'domain' => $domainAlias?->alias,
                    'url' => '/Ameise/unit/'.(int) $unit->id.'?ai_sales=1#prospecting-dossier',
                ],
                'match_type' => (int) $match->strength >= 90 ? 'exact' : 'probable',
                'reason_code' => $match->signal_code,
                'reason' => $this->duplicateReason((string) $match->signal_code),
                'confidence' => $match->strength,
                'rank' => $match->rank,
                'domain_match' => $domainAlias && $this->normalized_domain
                    ? hash_equals(mb_strtolower($domainAlias->alias), mb_strtolower($this->normalized_domain)) : null,
                'evidence_reference' => $match->evidence_reference,
                'evidence_hash' => $match->evidence_hash,
                'review_status' => $match->review_status,
            ];
        })->values()->all();
    }

    private function duplicateReason(string $code): string
    {
        return match ($code) {
            'exact_verified_public_domain' => 'Совпадает проверенный публичный домен.',
            'exact_corporate_email_domain' => 'Совпадает проверенный корпоративный email-домен.',
            'exact_normalized_public_phone' => 'Совпадает проверенный общий телефон.',
            'normalized_name_exact_city' => 'Совпадают нормализованное имя и город.',
            'normalized_name_exact_region' => 'Совпадают нормализованное имя и регион.',
            'normalized_or_fuzzy_name_review' => 'Имя совпадает или требует проверки похожести.',
            default => 'Обнаружен детерминированный identity signal.',
        };
    }

    private function workingNameOrigin(): string
    {
        return $this->loadedCollection('sources')->contains(
            fn ($source) => filled($source->title) && hash_equals((string) $source->title, (string) $this->working_name),
        ) ? 'source_page_title' : 'unverified_candidate_input';
    }

    private function safeUrl(?string $url, ?string $expectedDomain): ?string
    {
        if (! filled($url) || ! filled($expectedDomain)) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(mb_strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = preg_replace('/^www\./', '', mb_strtolower((string) $parts['host'])) ?? '';
        $domain = preg_replace('/^www\./', '', mb_strtolower($expectedDomain)) ?? '';

        return $host !== '' && hash_equals($domain, $host) ? $url : null;
    }

    private function boundedConfidence(string $key): ?int
    {
        $value = ($this->confidence_components ?? [])[$key] ?? null;

        return is_numeric($value) ? max(0, min(100, (int) $value)) : null;
    }

    private function loadedCollection(string $relation): Collection
    {
        return $this->resource->relationLoaded($relation) ? $this->resource->{$relation} : collect();
    }
}
