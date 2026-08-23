<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitProductMatchOrigin;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitProductMatchService
{
    public function __construct(
        private readonly AiToolDlpGuard $dlp,
        private readonly UnitDossierAuditLogger $audit,
        private readonly ProspectingFeatureGuard $features,
    ) {}

    public function suggest(Unit $unit, UnitBusinessContext $context, array $attributes, ?User $actor): UnitProductMatch
    {
        $this->features->dossier();
        if ((int) $context->unit_id !== (int) $unit->id) {
            throw ValidationException::withMessages(['context' => 'Product match context belongs to another Unit.']);
        }
        $type = $attributes['match_type'] instanceof UnitProductMatchType
            ? $attributes['match_type'] : UnitProductMatchType::from($attributes['match_type']);
        $this->assertDirection($context->lane, $type);
        $rationale = mb_substr(trim((string) $attributes['safe_rationale']), 0, 1000);
        $this->dlp->assertPayloadSafe(['safe_rationale' => $rationale], AiProcessingContour::LocalRu, $context->lane);

        return DB::transaction(function () use ($unit, $context, $attributes, $actor, $type, $rationale): UnitProductMatch {
            $match = UnitProductMatch::query()->firstOrCreate([
                'unit_business_context_id' => $context->id,
                'product_id' => (int) $attributes['product_id'],
                'match_type' => $type->value,
            ], [
                'unit_id' => $unit->id,
                'unit_source_id' => $attributes['unit_source_id'] ?? null,
                'prospecting_candidate_product_id' => $attributes['prospecting_candidate_product_id'] ?? null,
                'status' => UnitProductMatchStatus::Suggested,
                'origin' => $attributes['origin'] ?? UnitProductMatchOrigin::Manual,
                'evidence_confidence' => isset($attributes['evidence_confidence'])
                    ? max(0, min(100, (int) $attributes['evidence_confidence'])) : null,
                'safe_rationale' => $rationale,
                'evidence_reference' => isset($attributes['evidence_reference'])
                    ? mb_substr((string) $attributes['evidence_reference'], 0, 512) : null,
                'evidence_hash' => $attributes['evidence_hash'] ?? hash('sha256', $rationale),
                'rules_version' => $attributes['rules_version'] ?? null,
                'model_version' => null,
                'created_by' => $actor?->id,
                'stale_after' => $attributes['stale_after'] ?? now()->addDays(90),
            ]);
            if ((int) $match->unit_id !== (int) $unit->id) {
                throw ValidationException::withMessages(['product_id' => 'Product match identity conflicts with another Unit.']);
            }
            if ($match->wasRecentlyCreated) {
                $this->audit->record($unit, 'unit.product_match.suggested', 'Добавлено предложение связи Unit с Product.', $actor, $context, 'unit_product_match', $match->id, [
                    'product_id' => $match->product_id,
                    'match_type' => $type->value,
                    'origin' => $match->origin->value,
                ]);
            }

            return $match->fresh(['product:id,rus,eng', 'businessContext']);
        }, 3);
    }

    public function review(UnitProductMatch $match, UnitProductMatchStatus $status, User $actor): UnitProductMatch
    {
        $this->features->dossier();
        if (! in_array($status, [UnitProductMatchStatus::Reviewed, UnitProductMatchStatus::Approved, UnitProductMatchStatus::Rejected, UnitProductMatchStatus::Stale], true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported Product match review status.']);
        }
        $match->update(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->record($match->unit, 'unit.product_match.reviewed', "Product match отмечен как {$status->value}.", $actor, $match->businessContext, 'unit_product_match', $match->id, ['status' => $status->value]);

        return $match->fresh(['product:id,rus,eng', 'reviewer:id,name']);
    }

    private function assertDirection(BusinessLane $lane, UnitProductMatchType $type): void
    {
        if (($lane === BusinessLane::Sales && $type === UnitProductMatchType::PotentialOffer)
            || ($lane === BusinessLane::Procurement && $type === UnitProductMatchType::PotentialNeed)) {
            throw ValidationException::withMessages(['match_type' => 'Product match direction is opposite to the selected lane.']);
        }
    }
}
