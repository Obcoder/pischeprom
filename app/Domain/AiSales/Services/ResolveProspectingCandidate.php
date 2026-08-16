<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\Prospecting\CandidateResolutionDecision;
use App\Domain\AiSales\Enums\CandidateProductStatus;
use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Enums\ProspectingChannelKind;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\UnitAliasType;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchOrigin;
use App\Domain\AiSales\Enums\UnitProductMatchOrigin;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Email;
use App\Models\ProspectingCandidate;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitSource;
use App\Models\Uri;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveProspectingCandidate
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly UnitCandidateResolver $resolver,
        private readonly ProspectingCandidateNormalizer $normalizer,
        private readonly UnitBusinessContextService $contexts,
        private readonly UnitSourceService $sources,
        private readonly UnitAliasService $aliases,
        private readonly UnitObservationService $observations,
        private readonly UnitProductMatchService $productMatches,
        private readonly UnitGoodMatchService $goodMatches,
        private readonly GoodProductMappingResolver $productMappings,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function evaluate(ProspectingCandidate $candidate, User $actor): CandidateResolutionDecision
    {
        $this->features->dossier();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $candidate->lane);

        return DB::transaction(function () use ($candidate): CandidateResolutionDecision {
            $locked = ProspectingCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($locked->status->terminal()) {
                throw new DomainException('A terminal Candidate cannot be evaluated again.');
            }

            return $this->resolver->evaluate($locked);
        }, 3);
    }

    public function enrichExisting(ProspectingCandidate $candidate, Unit $unit, User $actor): Unit
    {
        $this->features->dossier();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::RESOLVE, $candidate->lane);

        return DB::transaction(function () use ($candidate, $unit, $actor): Unit {
            $locked = ProspectingCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            $lockedUnit = Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])
                ->lockForUpdate()->findOrFail($unit->id);
            if ($locked->status->terminal()) {
                if ($locked->resolved_unit_id === $lockedUnit->id) {
                    return $lockedUnit->fresh();
                }
                throw new DomainException('Candidate already has a terminal resolution.');
            }
            $this->assertApprovedJob($locked);
            $decision = $this->resolver->evaluate($locked);
            if (! in_array($decision->outcome, [CandidateResolutionOutcome::ExactExisting, CandidateResolutionOutcome::ProbableExistingReview], true)
                || ! in_array((int) $lockedUnit->id, array_map('intval', $decision->matchedUnitIds), true)) {
                throw ValidationException::withMessages(['unit_id' => 'Selected Unit is not an explainable deterministic match.']);
            }

            $context = $this->ensureContext($lockedUnit, $locked, $actor);
            $this->transferDossier($locked, $lockedUnit, $context, $actor);
            $locked->unitMatches()->where('unit_id', $lockedUnit->id)->update(['review_status' => 'accepted']);
            $locked->unitMatches()->where('unit_id', '!=', $lockedUnit->id)->update(['review_status' => 'dismissed']);
            $locked->update([
                'status' => ProspectingCandidateStatus::ExistingUnitEnriched,
                'resolution_outcome' => $decision->outcome,
                'resolved_unit_id' => $lockedUnit->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resolution_reason_code' => 'human_approved_existing_unit',
                'lock_version' => $locked->lock_version + 1,
                'expires_at' => now()->addDays((int) config('ai-sales.prospecting.retention.resolved_days', 14)),
            ]);
            $this->audit->record($lockedUnit, 'prospecting.candidate.resolved_existing', 'Кандидат привязан к существующему досье Unit после проверки.', $actor, $context, 'prospecting_candidate', $locked->id, [
                'candidate_public_id' => $locked->public_id,
                'outcome' => $decision->outcome->value,
            ]);

            return $lockedUnit->fresh();
        }, 3);
    }

    public function createNewUnit(ProspectingCandidate $candidate, User $actor): Unit
    {
        $this->features->dossier();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::RESOLVE, $candidate->lane);

        return DB::transaction(function () use ($candidate, $actor): Unit {
            $locked = ProspectingCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($locked->status === ProspectingCandidateStatus::NewUnitCreated && $locked->resolved_unit_id) {
                return Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->findOrFail($locked->resolved_unit_id);
            }
            if ($locked->status->terminal()) {
                throw new DomainException('Candidate already has a terminal resolution.');
            }
            $this->assertApprovedJob($locked);
            $decision = $this->resolver->evaluate($locked);
            if ($decision->outcome !== CandidateResolutionOutcome::NewUnitAllowed) {
                throw ValidationException::withMessages(['candidate' => 'Exact, probable, ambiguous, or invalid candidates cannot create another Unit.']);
            }
            if ($locked->sources()->count() < 1) {
                throw ValidationException::withMessages(['sources' => 'A valid provenance source is required.']);
            }
            $relevance = (int) ($locked->confidence_components['relevance'] ?? 0);
            if ($relevance < (int) config('ai-sales.prospecting.new_unit_min_relevance', 60)) {
                throw ValidationException::withMessages(['relevance' => 'Candidate is below the human-create relevance threshold.']);
            }
            $todayCount = ProspectingCandidate::query()->where('reviewed_by', $actor->id)
                ->where('status', ProspectingCandidateStatus::NewUnitCreated->value)
                ->whereDate('reviewed_at', now()->toDateString())->count();
            if ($todayCount >= (int) config('ai-sales.prospecting.limits.max_human_unit_creates_per_day', 20)) {
                throw ValidationException::withMessages(['rate' => 'Daily reviewed Unit creation limit reached.']);
            }

            $unit = Unit::query()->create([
                'name' => mb_substr($locked->working_name ?: (string) $locked->normalized_domain, 0, 255),
                'is_customer' => false,
                'is_supplier' => false,
            ]);
            $context = $this->ensureContext($unit, $locked, $actor);
            $this->transferDossier($locked, $unit, $context, $actor);
            $locked->update([
                'status' => ProspectingCandidateStatus::NewUnitCreated,
                'resolution_outcome' => CandidateResolutionOutcome::NewUnitAllowed,
                'resolved_unit_id' => $unit->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resolution_reason_code' => 'human_approved_new_unit',
                'lock_version' => $locked->lock_version + 1,
                'expires_at' => now()->addDays((int) config('ai-sales.prospecting.retention.resolved_days', 14)),
            ]);
            $this->audit->record($unit, 'prospecting.candidate.created_unit', 'После human review создано рабочее досье Unit; Entity не создавалась.', $actor, $context, 'prospecting_candidate', $locked->id, [
                'candidate_public_id' => $locked->public_id,
                'entity_mutation' => false,
            ]);

            return $unit->fresh();
        }, 3);
    }

    public function reject(ProspectingCandidate $candidate, User $actor, string $reasonCode): ProspectingCandidate
    {
        $this->features->dossier();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $candidate->lane);
        $allowed = ['irrelevant', 'invalid_source', 'duplicate_ambiguous', 'policy_blocked'];
        if (! in_array($reasonCode, $allowed, true)) {
            throw ValidationException::withMessages(['reason_code' => 'Unsupported rejection reason.']);
        }

        return DB::transaction(function () use ($candidate, $actor, $reasonCode): ProspectingCandidate {
            $locked = ProspectingCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($locked->status === ProspectingCandidateStatus::Rejected && $locked->resolution_reason_code === $reasonCode) {
                return $locked;
            }
            if ($locked->status->terminal()) {
                throw new DomainException('A terminal Candidate cannot be rejected again.');
            }
            $locked->update([
                'status' => ProspectingCandidateStatus::Rejected,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resolution_reason_code' => $reasonCode,
                'expires_at' => now()->addDays((int) config('ai-sales.prospecting.retention.rejected_days', 7)),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $locked->unitMatches()->update(['review_status' => 'rejected']);

            return $locked->fresh();
        }, 3);
    }

    private function assertApprovedJob(ProspectingCandidate $candidate): void
    {
        $candidate->loadMissing('job');
        if (! $candidate->job || $candidate->job->status !== ProspectingJobStatus::Approved) {
            throw ValidationException::withMessages(['job' => 'Candidate resolution requires a human-approved job.']);
        }
    }

    private function ensureContext(Unit $unit, ProspectingCandidate $candidate, User $actor): UnitBusinessContext
    {
        return $this->contexts->upsert($unit, [
            'lane' => $candidate->lane,
            'role_code' => $candidate->role_code,
            'stage' => UnitContextStage::Researching,
            'status' => UnitContextStatus::Active,
            'confidence' => $candidate->confidence_components['relevance'] ?? null,
            'owner_user_id' => $actor->id,
            'source' => 'prospecting_stage08r_product_first',
        ], $actor);
    }

    private function transferDossier(
        ProspectingCandidate $candidate,
        Unit $unit,
        UnitBusinessContext $context,
        User $actor,
    ): void {
        $candidate->loadMissing(['sources', 'channels', 'products.product', 'job.goods']);
        $approvedCandidateProducts = $candidate->products
            ->filter(fn ($candidateProduct) => $candidateProduct->status === CandidateProductStatus::Approved);
        if ($approvedCandidateProducts->isEmpty()) {
            throw ValidationException::withMessages(['products' => 'Resolution requires an approved Candidate Product scope.']);
        }
        $visibility = $candidate->lane->value === 'sales'
            ? UnitVisibilityScope::SalesLane : UnitVisibilityScope::ProcurementLane;
        $unitSources = collect();
        foreach ($candidate->sources as $source) {
            $unitSources->push($this->sources->create($unit, [
                'unit_business_context_id' => $context->id,
                'source_type' => 'prospecting_candidate',
                'source_label' => $source->title ?: 'Prospecting candidate source',
                'source_reference' => 'prospecting:evidence:'.$source->evidence_hash,
                'source_url' => $source->canonical_url,
                'data_classification' => DataClassification::Public->value,
                'visibility_scope' => $visibility->value,
                'observed_at' => $source->accessed_at ?? now(),
            ], $actor));
        }
        /** @var UnitSource|null $primarySource */
        $primarySource = $unitSources->first();
        if ($this->normalizer->normalizeName($unit->name) !== $candidate->normalized_name && $candidate->working_name !== '') {
            $this->addAliasIfMissing($unit, $context, [
                'unit_business_context_id' => $context->id,
                'unit_source_id' => $primarySource?->id,
                'alias' => $candidate->working_name,
                'alias_type' => UnitAliasType::TradeName->value,
                'confidence' => $candidate->confidence_components['identity'] ?? null,
                'data_classification' => DataClassification::Public->value,
                'visibility_scope' => $visibility->value,
            ], $actor);
        }
        if ($candidate->normalized_domain) {
            $this->addAliasIfMissing($unit, $context, [
                'unit_business_context_id' => $context->id,
                'unit_source_id' => $primarySource?->id,
                'alias' => $candidate->normalized_domain,
                'alias_type' => UnitAliasType::DomainName->value,
                'confidence' => $candidate->confidence_components['identity'] ?? null,
                'data_classification' => DataClassification::Public->value,
                'visibility_scope' => $visibility->value,
            ], $actor);
        }
        foreach ([
            'public_activity' => $candidate->public_activity_summary,
            'prospecting_relevance' => $candidate->relevance_summary,
        ] as $key => $summary) {
            if (filled($summary)) {
                $this->observations->create($unit, [
                    'unit_business_context_id' => $context->id,
                    'unit_source_id' => $primarySource?->id,
                    'observation_key' => $key,
                    'summary' => $summary,
                    'source_reference' => $primarySource?->source_reference,
                    'confidence' => $candidate->confidence_components['relevance'] ?? null,
                    'data_classification' => DataClassification::Public->value,
                    'visibility_scope' => $visibility->value,
                    'rules_version' => ProspectingCandidateNormalizer::RULES_VERSION,
                ], $actor);
            }
        }
        foreach ($candidate->channels as $channel) {
            $this->transferChannel($unit, $context, $primarySource, $channel, $actor, $visibility);
        }
        $createdProductMatches = collect();
        foreach ($approvedCandidateProducts as $candidateProduct) {
            $productMatch = $this->productMatches->suggest($unit, $context, [
                'product_id' => $candidateProduct->product_id,
                'unit_source_id' => $primarySource?->id,
                'prospecting_candidate_product_id' => $candidateProduct->id,
                'match_type' => $candidate->purpose->productMatchType(),
                'evidence_confidence' => $candidateProduct->confidence,
                'safe_rationale' => $candidateProduct->safe_rationale,
                'evidence_reference' => $candidateProduct->evidence_reference,
                'evidence_hash' => $candidateProduct->evidence_hash,
                'origin' => UnitProductMatchOrigin::Candidate,
                'rules_version' => ProspectingCandidateNormalizer::RULES_VERSION,
            ], $actor);
            $createdProductMatches->put((int) $candidateProduct->product_id, $productMatch);
        }
        foreach ($candidate->job?->goods ?? [] as $good) {
            $productId = $this->productMappings->exactProductId((int) $good->id);
            $productMatch = $productId ? $createdProductMatches->get($productId) : null;
            if (! $productMatch) {
                continue;
            }
            $this->goodMatches->suggest($unit, $context, [
                'unit_product_match_id' => $productMatch->id,
                'good_id' => $good->id,
                'unit_source_id' => $primarySource?->id,
                'prospecting_candidate_id' => $candidate->id,
                'match_type' => $candidate->purpose->goodMatchType(),
                'fit_confidence' => 0,
                'confidence' => null,
                'safe_rationale' => 'Human-selected originating Good maps exactly to this Product; commercial fit remains unscored and review-required.',
                'evidence_reference' => $primarySource?->source_reference,
                'evidence_hash' => hash('sha256', $candidate->normalized_payload_hash.'|good|'.$good->id.'|product|'.$productId),
                'origin' => UnitGoodMatchOrigin::Candidate,
                'rules_version' => ProspectingCandidateNormalizer::RULES_VERSION,
            ], $actor);
        }
    }

    private function transferChannel(
        Unit $unit,
        UnitBusinessContext $context,
        ?UnitSource $source,
        $channel,
        User $actor,
        UnitVisibilityScope $laneVisibility,
    ): void {
        $value = (string) $channel->protected_value;
        $references = ['email_id' => null, 'telephone_id' => null, 'uri_id' => null];
        if ($channel->channel_kind === ProspectingChannelKind::Email) {
            $record = Email::withTrashed()->firstOrCreate(['address' => $value], ['source' => 'prospecting_stage08', 'is_active' => true]);
            if ($record->trashed()) {
                return;
            }
            $unit->emails()->syncWithoutDetaching([$record->id]);
            $references['email_id'] = $record->id;
        } elseif ($channel->channel_kind === ProspectingChannelKind::Telephone) {
            $record = Telephone::query()->firstOrCreate(['number' => $value]);
            $unit->telephones()->syncWithoutDetaching([$record->id]);
            $references['telephone_id'] = $record->id;
        } else {
            $record = Uri::query()->firstOrCreate(['address' => $value]);
            $unit->uris()->syncWithoutDetaching([$record->id]);
            $references['uri_id'] = $record->id;
        }
        $personal = $channel->contact_role === 'person_specific';
        $referenceColumn = $channel->channel_kind === ProspectingChannelKind::Email
            ? 'email_id'
            : ($channel->channel_kind === ProspectingChannelKind::Telephone ? 'telephone_id' : 'uri_id');
        $existingQuery = UnitContactContextLink::query()
            ->where('unit_id', $unit->id)
            ->where('unit_business_context_id', $context->id)
            ->where('channel_type', $channel->channel_kind->value);
        $link = (clone $existingQuery)->whereNull('archived_at')
            ->where('normalized_hash', $channel->normalized_hash)->lockForUpdate()->first();
        $link ??= (clone $existingQuery)->whereNull('archived_at')
            ->where($referenceColumn, $references[$referenceColumn])->lockForUpdate()->first();
        if ($link) {
            $updates = ['last_seen_at' => now()];
            if ($link->normalized_hash === null) {
                $updates['normalized_hash'] = $channel->normalized_hash;
            }
            if ($personal && $link->data_classification !== DataClassification::PersonalData) {
                $updates['data_classification'] = DataClassification::PersonalData;
                $updates['visibility_scope'] = UnitVisibilityScope::InternalOnly;
                $updates['review_required'] = true;
            }
            if (in_array($channel->communication_state->value, ['do_not_contact', 'suppressed'], true)
                && ! in_array($link->communication_state->value, ['do_not_contact', 'suppressed'], true)) {
                $updates['communication_state'] = $channel->communication_state->value;
            }
            $link->update($updates);

            return;
        }
        if ((clone $existingQuery)->whereNotNull('archived_at')
            ->where(function ($query) use ($channel, $referenceColumn, $references): void {
                $query->where('normalized_hash', $channel->normalized_hash)
                    ->orWhere($referenceColumn, $references[$referenceColumn]);
            })->exists()) {
            return;
        }
        UnitContactContextLink::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'channel_type' => $channel->channel_kind->value,
            'normalized_hash' => $channel->normalized_hash,
            'unit_source_id' => $source?->id,
            ...$references,
            'channel_value_snapshot' => $channel->masked_display,
            'contact_role' => $channel->contact_role,
            'verification_status' => $channel->verification_status,
            'confidence' => $channel->confidence,
            'data_classification' => $personal ? DataClassification::PersonalData : DataClassification::Public,
            'visibility_scope' => $personal ? UnitVisibilityScope::InternalOnly : $laneVisibility,
            'communication_state' => $channel->communication_state,
            'review_required' => true,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_verified_at' => null,
            'created_by' => $actor->id,
        ]);
    }

    private function addAliasIfMissing(
        Unit $unit,
        UnitBusinessContext $context,
        array $attributes,
        User $actor,
    ): void {
        $normalized = $this->aliases->normalize((string) $attributes['alias']);
        $exists = UnitAlias::query()
            ->where('unit_id', $unit->id)
            ->where('unit_business_context_id', $context->id)
            ->where('normalized_alias', $normalized)
            ->where('alias_type', $attributes['alias_type'])
            ->exists();

        if (! $exists) {
            $this->aliases->create($unit, $attributes, $actor);
        }
    }
}
