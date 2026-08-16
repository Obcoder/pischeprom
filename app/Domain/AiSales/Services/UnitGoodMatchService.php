<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitGoodMatchOrigin;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchType;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitGoodMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitGoodMatchService
{
    public function __construct(
        private readonly AiToolDlpGuard $dlp,
        private readonly UnitDossierAuditLogger $audit,
        private readonly ProspectingFeatureGuard $features,
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
        $rationale = mb_substr(trim((string) $attributes['safe_rationale']), 0, 1000);
        $this->dlp->assertPayloadSafe(['safe_rationale' => $rationale], AiProcessingContour::LocalRu, $context->lane);
        $status = UnitGoodMatchStatus::Suggested;

        return DB::transaction(function () use ($unit, $context, $attributes, $actor, $type, $rationale, $status): UnitGoodMatch {
            $match = UnitGoodMatch::query()->firstOrCreate([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'good_id' => (int) $attributes['good_id'],
                'match_type' => $type->value,
            ], [
                'unit_source_id' => $attributes['unit_source_id'] ?? null,
                'prospecting_candidate_id' => $attributes['prospecting_candidate_id'] ?? null,
                'relevance' => max(0, min(100, (int) $attributes['relevance'])),
                'confidence' => isset($attributes['confidence']) ? max(0, min(100, (int) $attributes['confidence'])) : null,
                'safe_rationale' => $rationale,
                'evidence_reference' => isset($attributes['evidence_reference']) ? mb_substr((string) $attributes['evidence_reference'], 0, 512) : null,
                'evidence_hash' => $attributes['evidence_hash'] ?? hash('sha256', $rationale),
                'status' => $status->value,
                'origin' => $attributes['origin'] ?? UnitGoodMatchOrigin::Manual,
                'rules_version' => $attributes['rules_version'] ?? null,
                'model_version' => null,
                'created_by' => $actor?->id,
                'stale_after' => $attributes['stale_after'] ?? now()->addDays(90),
            ]);
            if ($match->wasRecentlyCreated) {
                $this->audit->record($unit, 'unit.good_match.suggested', 'Добавлено предложение связи Unit с товаром.', $actor, $context, 'unit_good_match', $match->id, [
                    'good_id' => $match->good_id,
                    'match_type' => $type->value,
                    'origin' => $match->origin->value,
                ]);
            }

            return $match->fresh(['good:id,name', 'businessContext']);
        }, 3);
    }

    public function review(UnitGoodMatch $match, UnitGoodMatchStatus $status, User $actor): UnitGoodMatch
    {
        $this->features->dossier();
        if (! in_array($status, [UnitGoodMatchStatus::Reviewed, UnitGoodMatchStatus::Approved, UnitGoodMatchStatus::Rejected, UnitGoodMatchStatus::Stale], true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported review status.']);
        }
        $match->update(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->record($match->unit, 'unit.good_match.reviewed', "Good match отмечен как {$status->value}.", $actor, $match->businessContext, 'unit_good_match', $match->id, ['status' => $status->value]);

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
