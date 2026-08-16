<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\ProductMappingState;
use App\Domain\AiSales\Enums\UnitGoodMatchOrigin;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchType;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitGoodMatchService
{
    public function __construct(
        private readonly AiToolDlpGuard $dlp,
        private readonly UnitDossierAuditLogger $audit,
        private readonly ProspectingFeatureGuard $features,
        private readonly GoodProductMappingResolver $productMappings,
    ) {}

    public function suggest(Unit $unit, UnitBusinessContext $context, array $attributes, ?User $actor): UnitGoodMatch
    {
        $this->features->dossier();
        if ((int) $context->unit_id !== (int) $unit->id) {
            throw ValidationException::withMessages(['context' => 'Good match context belongs to another Unit.']);
        }
        $type = $attributes['match_type'] instanceof UnitGoodMatchType
            ? $attributes['match_type'] : UnitGoodMatchType::from($attributes['match_type']);
        $this->assertDirection($context->lane, $type);
        $productMatch = UnitProductMatch::query()->findOrFail((int) ($attributes['unit_product_match_id'] ?? 0));
        if ((int) $productMatch->unit_id !== (int) $unit->id
            || (int) $productMatch->unit_business_context_id !== (int) $context->id
            || $productMatch->match_type->value !== $type->value) {
            throw ValidationException::withMessages(['unit_product_match_id' => 'Good fit must use the matching context-bound Product relation.']);
        }
        $goodId = (int) $attributes['good_id'];
        if ($this->productMappings->state($goodId, [(int) $productMatch->product_id]) !== ProductMappingState::Mapped) {
            throw ValidationException::withMessages(['good_id' => 'Good fit requires one exact mapping to the selected Product.']);
        }
        $rationale = mb_substr(trim((string) $attributes['safe_rationale']), 0, 1000);
        $this->dlp->assertPayloadSafe(['safe_rationale' => $rationale], AiProcessingContour::LocalRu, $context->lane);
        $status = UnitGoodMatchStatus::Suggested;
        $fitStatus = GoodOfferFitStatus::OfferCandidate;

        return DB::transaction(function () use ($unit, $context, $attributes, $actor, $type, $rationale, $status, $fitStatus, $productMatch): UnitGoodMatch {
            $match = UnitGoodMatch::query()->where([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'good_id' => (int) $attributes['good_id'],
                'match_type' => $type->value,
            ])->lockForUpdate()->first();
            if ($match && ! $match->unit_product_match_id) {
                throw ValidationException::withMessages(['good_id' => 'Legacy Good match requires explicit reconciliation before new use.']);
            }
            $match ??= UnitGoodMatch::query()->create([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'unit_product_match_id' => $productMatch->id,
                'good_id' => (int) $attributes['good_id'],
                'unit_source_id' => $attributes['unit_source_id'] ?? null,
                'prospecting_candidate_id' => $attributes['prospecting_candidate_id'] ?? null,
                'match_type' => $type->value,
                'relevance' => max(0, min(100, (int) ($attributes['fit_confidence'] ?? 0))),
                'confidence' => isset($attributes['confidence']) ? max(0, min(100, (int) $attributes['confidence'])) : null,
                'safe_rationale' => $rationale,
                'evidence_reference' => isset($attributes['evidence_reference']) ? mb_substr((string) $attributes['evidence_reference'], 0, 512) : null,
                'evidence_hash' => $attributes['evidence_hash'] ?? hash('sha256', $rationale),
                'status' => $status->value,
                'fit_status' => $fitStatus->value,
                'compatibility_state' => ProductMappingState::Mapped->value,
                'origin' => $attributes['origin'] ?? UnitGoodMatchOrigin::Manual,
                'rules_version' => $attributes['rules_version'] ?? null,
                'model_version' => null,
                'created_by' => $actor?->id,
                'stale_after' => $attributes['stale_after'] ?? now()->addDays(90),
            ]);
            if ((int) $match->unit_product_match_id !== (int) $productMatch->id) {
                throw ValidationException::withMessages(['good_id' => 'Good fit is already bound to another Product match.']);
            }
            if ($match->wasRecentlyCreated) {
                $this->audit->record($unit, 'unit.good_offer_fit.suggested', 'Добавлено предложение конкретного Good offer fit.', $actor, $context, 'unit_good_match', $match->id, [
                    'good_id' => $match->good_id,
                    'unit_product_match_id' => $productMatch->id,
                    'fit_status' => $fitStatus->value,
                    'origin' => $match->origin->value,
                ]);
            }

            return $match->fresh(['good:id,name', 'businessContext']);
        }, 3);
    }

    public function review(UnitGoodMatch $match, GoodOfferFitStatus $status, User $actor): UnitGoodMatch
    {
        $this->features->dossier();
        if (! $match->unit_product_match_id || $match->compatibility_state !== ProductMappingState::Mapped) {
            throw ValidationException::withMessages(['match' => 'Legacy or unresolved Good rows require Product reconciliation first.']);
        }
        $legacyStatus = match ($status) {
            GoodOfferFitStatus::OfferCandidate => UnitGoodMatchStatus::Suggested,
            GoodOfferFitStatus::PreferredOffer => UnitGoodMatchStatus::Reviewed,
            GoodOfferFitStatus::ApprovedForOffer, GoodOfferFitStatus::Quoted => UnitGoodMatchStatus::Approved,
            GoodOfferFitStatus::Rejected => UnitGoodMatchStatus::Rejected,
            GoodOfferFitStatus::Stale => UnitGoodMatchStatus::Stale,
        };
        $match->update(['fit_status' => $status, 'status' => $legacyStatus, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->record($match->unit, 'unit.good_offer_fit.reviewed', "Good offer fit отмечен как {$status->value}.", $actor, $match->businessContext, 'unit_good_match', $match->id, ['fit_status' => $status->value]);

        return $match->fresh(['good:id,name', 'reviewer:id,name']);
    }

    private function assertDirection(BusinessLane $lane, UnitGoodMatchType $type): void
    {
        if (($lane === BusinessLane::Sales && $type === UnitGoodMatchType::PotentialOffer)
            || ($lane === BusinessLane::Procurement && $type === UnitGoodMatchType::PotentialNeed)) {
            throw ValidationException::withMessages(['match_type' => 'Good match direction is opposite to the selected lane.']);
        }
    }
}
