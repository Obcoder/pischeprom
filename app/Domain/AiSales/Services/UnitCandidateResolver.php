<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\Prospecting\CandidateResolutionDecision;
use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingCandidateUnitMatch;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\UnitContactContextLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UnitCandidateResolver
{
    public function __construct(private readonly ProspectingCandidateNormalizer $normalizer) {}

    public function evaluate(ProspectingCandidate $candidate): CandidateResolutionDecision
    {
        $candidate->loadMissing(['sources:id,prospecting_candidate_id,evidence_hash', 'channels']);
        if ($candidate->sources->isEmpty() || ($candidate->normalized_name === '' && $candidate->normalized_domain === null)) {
            return $this->persist($candidate, new CandidateResolutionDecision(
                CandidateResolutionOutcome::RejectedInvalid,
                ['valid_source_missing'], [], [], ['valid_source' => 0], true,
            ));
        }

        $signals = collect();
        if ($candidate->canonical_website) {
            $this->linkedUnitsByHash($candidate, hash('sha256', 'uri|'.$candidate->canonical_website), 'exact_verified_public_domain', 100)
                ->each(fn ($signal) => $signals->push($signal));
        }
        $candidate->channels->each(function ($channel) use ($candidate, $signals): void {
            if ($channel->channel_kind->value === 'telephone'
                && $channel->contact_role === 'business_general'
                && $channel->data_classification === DataClassification::Public) {
                $this->linkedUnitsByHash($candidate, $channel->normalized_hash, 'exact_normalized_public_phone', 90)
                    ->each(fn ($signal) => $signals->push($signal));
            }
            if ($channel->channel_kind->value === 'email'
                && $channel->contact_role === 'business_general'
                && $channel->data_classification === DataClassification::Public) {
                $domain = substr(strrchr((string) $channel->protected_value, '@') ?: '', 1);
                if ($domain !== '') {
                    UnitContactContextLink::query()
                        ->select(['unit_contact_context_links.unit_id'])
                        ->join('unit_business_contexts', 'unit_business_contexts.id', '=', 'unit_contact_context_links.unit_business_context_id')
                        ->join('emails', 'emails.id', '=', 'unit_contact_context_links.email_id')
                        ->where('unit_business_contexts.lane', $candidate->lane->value)
                        ->where('emails.domain', $domain)
                        ->where('unit_contact_context_links.contact_role', 'business_general')
                        ->where('unit_contact_context_links.verification_status', ObservationVerificationStatus::Verified->value)
                        ->where('unit_contact_context_links.data_classification', DataClassification::Public->value)
                        ->whereIn('unit_contact_context_links.visibility_scope', [
                            UnitVisibilityScope::SharedPublic->value,
                            $this->laneVisibility($candidate)->value,
                        ])
                        ->whereNull('unit_contact_context_links.archived_at')
                        ->distinct()->limit(20)->pluck('unit_contact_context_links.unit_id')
                        ->each(fn ($unitId) => $signals->push($this->signal((int) $unitId, 'exact_corporate_email_domain', 95)));
                }
            }
        });

        $strong = $signals->where('strength', '>=', 90)->values();
        $strongUnitCount = $strong->pluck('unit_id')->unique()->count();
        if ($strongUnitCount === 1) {
            return $this->persist($candidate, $this->decision(CandidateResolutionOutcome::ExactExisting, $strong), $strong);
        }
        if ($strongUnitCount > 1) {
            return $this->persist($candidate, $this->decision(CandidateResolutionOutcome::ProbableExistingReview, $strong), $strong);
        }

        $nameLocation = $this->nameAndLocationSignals($candidate);
        if ($nameLocation->isNotEmpty()) {
            return $this->persist($candidate, $this->decision(CandidateResolutionOutcome::ProbableExistingReview, $nameLocation), $nameLocation);
        }

        $nameOnly = $this->nameOnlySignals($candidate);
        if ($nameOnly->isNotEmpty()) {
            return $this->persist($candidate, $this->decision(CandidateResolutionOutcome::ProbableExistingReview, $nameOnly), $nameOnly);
        }

        return $this->persist($candidate, new CandidateResolutionDecision(
            CandidateResolutionOutcome::NewUnitAllowed,
            ['no_existing_identity_signal'], [], $candidate->sources->pluck('evidence_hash')->all(),
            ['valid_source' => 100, 'identity_match' => 0], true,
        ));
    }

    private function linkedUnitsByHash(ProspectingCandidate $candidate, string $hash, string $code, int $strength): Collection
    {
        return UnitContactContextLink::query()
            ->select(['unit_contact_context_links.unit_id'])
            ->join('unit_business_contexts', 'unit_business_contexts.id', '=', 'unit_contact_context_links.unit_business_context_id')
            ->where('unit_business_contexts.lane', $candidate->lane->value)
            ->where('unit_contact_context_links.normalized_hash', $hash)
            ->where('unit_contact_context_links.contact_role', 'business_general')
            ->where('unit_contact_context_links.verification_status', ObservationVerificationStatus::Verified->value)
            ->where('unit_contact_context_links.data_classification', DataClassification::Public->value)
            ->whereIn('unit_contact_context_links.visibility_scope', [
                UnitVisibilityScope::SharedPublic->value,
                $this->laneVisibility($candidate)->value,
            ])
            ->whereNull('unit_contact_context_links.archived_at')
            ->distinct()->limit(20)->pluck('unit_contact_context_links.unit_id')
            ->map(fn ($unitId) => $this->signal((int) $unitId, $code, $strength));
    }

    private function nameAndLocationSignals(ProspectingCandidate $candidate): Collection
    {
        if ((! $candidate->city_id && ! $candidate->region_id) || $candidate->normalized_name === '') {
            return collect();
        }
        $locationConstraint = function ($query) use ($candidate): void {
            if ($candidate->city_id) {
                $query->where('cities.id', $candidate->city_id);

                return;
            }
            $query->where('cities.region_id', $candidate->region_id);
        };
        $unitIds = $this->visibleAliases($candidate)
            ->where('normalized_alias', $candidate->normalized_name)
            ->whereHas('unit.cities', $locationConstraint)
            ->limit(20)->pluck('unit_id');
        Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])
            ->whereHas('cities', $locationConstraint)
            ->select(['id', 'name'])->limit(100)->get()
            ->filter(fn (Unit $unit) => $this->normalizer->normalizeName($unit->name) === $candidate->normalized_name)
            ->pluck('id')->each(fn ($id) => $unitIds->push($id));

        $signalCode = $candidate->city_id ? 'normalized_name_exact_city' : 'normalized_name_exact_region';

        return $unitIds->unique()->values()->map(fn ($unitId) => $this->signal((int) $unitId, $signalCode, 70));
    }

    private function nameOnlySignals(ProspectingCandidate $candidate): Collection
    {
        if ($candidate->normalized_name === '') {
            return collect();
        }
        $unitIds = $this->visibleAliases($candidate)->where('normalized_alias', $candidate->normalized_name)
            ->limit(20)->pluck('unit_id');
        Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])
            ->where('name', $candidate->working_name)->select(['id', 'name'])->limit(20)->get()
            ->filter(fn (Unit $unit) => $this->normalizer->normalizeName($unit->name) === $candidate->normalized_name)
            ->pluck('id')->each(fn ($id) => $unitIds->push($id));

        if ($unitIds->isEmpty() && mb_strlen($candidate->normalized_name) >= 4) {
            $prefix = mb_substr($candidate->normalized_name, 0, 3);
            Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])
                ->where('name', 'like', $prefix.'%')->select(['id', 'name'])->limit(250)->get()
                ->filter(function (Unit $unit) use ($candidate): bool {
                    similar_text(
                        $this->normalizer->normalizeName($unit->name),
                        $candidate->normalized_name,
                        $percentage,
                    );

                    return $percentage >= 85;
                })->pluck('id')->each(fn ($id) => $unitIds->push($id));
        }

        return $unitIds->unique()->values()->map(fn ($unitId) => $this->signal((int) $unitId, 'normalized_or_fuzzy_name_review', 45));
    }

    private function signal(int $unitId, string $code, int $strength): array
    {
        return ['unit_id' => $unitId, 'signal_code' => $code, 'strength' => $strength, 'evidence_reference' => 'deterministic:'.$code];
    }

    private function decision(CandidateResolutionOutcome $outcome, Collection $signals): CandidateResolutionDecision
    {
        return new CandidateResolutionDecision(
            $outcome,
            $signals->pluck('signal_code')->unique()->values()->all(),
            $signals->pluck('unit_id')->unique()->values()->all(),
            $signals->pluck('evidence_reference')->unique()->values()->all(),
            ['identity_match' => (int) $signals->max('strength')],
            true,
        );
    }

    private function persist(
        ProspectingCandidate $candidate,
        CandidateResolutionDecision $decision,
        ?Collection $signals = null,
    ): CandidateResolutionDecision {
        $status = match ($decision->outcome) {
            CandidateResolutionOutcome::ExactExisting => ProspectingCandidateStatus::ExactExistingUnit,
            CandidateResolutionOutcome::ProbableExistingReview => ProspectingCandidateStatus::ProbableExistingReview,
            CandidateResolutionOutcome::NewUnitAllowed => ProspectingCandidateStatus::NewUnitReview,
            CandidateResolutionOutcome::RejectedInvalid => ProspectingCandidateStatus::Rejected,
        };
        if (! $candidate->status->terminal()) {
            $candidate->update([
                'status' => $status,
                'resolution_outcome' => $decision->outcome,
                'resolution_reason_code' => $decision->signalCodes[0] ?? null,
                'confidence_components' => [
                    ...($candidate->confidence_components ?? []),
                    ...$decision->confidenceComponents,
                ],
                'lock_version' => $candidate->lock_version + 1,
            ]);
        }
        $matchedSignals = ($signals ?? collect())->unique(fn (array $signal) => $signal['unit_id'].'|'.$signal['signal_code']);
        foreach ($matchedSignals as $signal) {
            $unitId = (int) $signal['unit_id'];
            $code = (string) $signal['signal_code'];
            $rank = array_search($unitId, array_map('intval', $decision->matchedUnitIds), true);
            ProspectingCandidateUnitMatch::query()->updateOrCreate([
                'prospecting_candidate_id' => $candidate->id,
                'unit_id' => $unitId,
                'signal_code' => $code,
            ], [
                'strength' => (int) $signal['strength'],
                'rank' => ($rank === false ? 0 : $rank) + 1,
                'evidence_hash' => hash('sha256', $candidate->fingerprint_hash.'|'.$unitId.'|'.$code),
                'evidence_reference' => $signal['evidence_reference'] ?? null,
                'review_status' => 'suggested',
            ]);
        }

        return $decision;
    }

    private function visibleAliases(ProspectingCandidate $candidate): Builder
    {
        $laneScope = $this->laneVisibility($candidate)->value;

        return UnitAlias::query()
            ->where('data_classification', DataClassification::Public->value)
            ->where(function ($query) use ($candidate, $laneScope): void {
                $query->where('visibility_scope', UnitVisibilityScope::SharedPublic->value)
                    ->orWhere(function ($laneQuery) use ($candidate, $laneScope): void {
                        $laneQuery->where('visibility_scope', $laneScope)
                            ->whereHas('businessContext', fn ($context) => $context->where('lane', $candidate->lane->value));
                    });
            });
    }

    private function laneVisibility(ProspectingCandidate $candidate): UnitVisibilityScope
    {
        return $candidate->lane->value === 'sales'
            ? UnitVisibilityScope::SalesLane
            : UnitVisibilityScope::ProcurementLane;
    }
}
