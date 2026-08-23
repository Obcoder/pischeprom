<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\ProspectingCommunicationState;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Queries\UnitTransactionAggregateQuery;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProspectPriorityInputAssembler
{
    public function __construct(
        private readonly UnitContextAuthorizationService $authorization,
        private readonly UnitTransactionAggregateQuery $transactions,
    ) {}

    public function assemble(User $actor, UnitBusinessContext $subject): ScoringInput
    {
        $context = UnitBusinessContext::query()->select([
            'id', 'unit_id', 'lane', 'role_code', 'stage', 'status', 'last_activity_at', 'updated_at',
        ])->findOrFail($subject->id);
        $this->authorization->authorizeLane($actor, $context->lane);
        $laneScope = $context->lane === BusinessLane::Sales
            ? UnitVisibilityScope::SalesLane->value : UnitVisibilityScope::ProcurementLane->value;

        $snapshotRows = DB::table('unit_product_relevance_snapshots as s')
            ->join('unit_product_matches as m', 'm.id', '=', 's.unit_product_match_id')
            ->where('s.unit_business_context_id', $context->id)
            ->whereNull('s.stale_at')->whereNull('s.superseded_at')
            ->whereNotIn('m.status', [UnitProductMatchStatus::Rejected->value, UnitProductMatchStatus::Stale->value])
            ->select(['s.id', 's.unit_product_match_id', 's.computed_score', 's.confidence', 's.review_status', 's.created_at', 'm.status as match_status'])
            ->orderByDesc('s.id')->limit(100)->get()->unique('unit_product_match_id')->take(20)->values();
        $productScores = $snapshotRows->map(static fn ($row): array => [
            'snapshot_id' => (int) $row->id,
            'score' => (int) $row->computed_score,
            'confidence' => (int) $row->confidence,
            'review_status' => (string) $row->review_status,
            'match_status' => (string) $row->match_status,
        ])->all();

        $contacts = DB::table('unit_contact_context_links')->where('unit_id', $context->unit_id)
            ->where('unit_business_context_id', $context->id)->whereNull('archived_at')
            ->select(['id', 'contact_role', 'verification_status', 'data_classification', 'visibility_scope', 'communication_state', 'review_required', 'last_verified_at'])
            ->orderBy('id')->limit(100)->get();
        $visibleContacts = $contacts->filter(fn ($row): bool => $row->data_classification === DataClassification::Public->value
            && in_array($row->visibility_scope, [UnitVisibilityScope::SharedPublic->value, $laneScope], true));
        $verifiedChannel = $visibleContacts->contains(fn ($row): bool => $row->verification_status === ObservationVerificationStatus::Verified->value
            && $row->contact_role !== 'person_specific');
        $doNotContact = $context->stage === UnitContextStage::DoNotContact
            || $contacts->contains('communication_state', ProspectingCommunicationState::DoNotContact->value);
        $suppressed = $contacts->contains('communication_state', ProspectingCommunicationState::Suppressed->value);

        $sources = DB::table('unit_sources')->where('unit_id', $context->unit_id)
            ->where(function ($query) use ($context): void {
                $query->where('unit_business_context_id', $context->id)->orWhereNull('unit_business_context_id');
            })
            ->where('data_classification', DataClassification::Public->value)
            ->whereIn('visibility_scope', [UnitVisibilityScope::SharedPublic->value, $laneScope])
            ->select(['id', 'source_key', 'observed_at', 'last_checked_at'])->orderByDesc('id')->limit(100)->get();
        $freshCount = $sources->filter(function ($row): bool {
            $date = $row->last_checked_at ?: $row->observed_at;

            return $date !== null && now()->diffInDays($date, absolute: true) <= 180;
        })->count();
        $freshness = $sources->isEmpty() ? 0 : (int) round($freshCount / $sources->count() * 100);

        $geographyEvidence = DB::table('unit_observations')->where('unit_id', $context->unit_id)
            ->where(function ($query) use ($context): void {
                $query->where('unit_business_context_id', $context->id)->orWhereNull('unit_business_context_id');
            })
            ->whereIn('observation_key', ['unit.location_summary'])
            ->where('verification_status', ObservationVerificationStatus::Verified->value)
            ->where('data_classification', DataClassification::Public->value)
            ->whereIn('visibility_scope', [UnitVisibilityScope::SharedPublic->value, $laneScope])
            ->exists();
        $unresolvedDuplicate = DB::table('prospecting_candidate_unit_matches as pum')
            ->join('prospecting_candidates as pc', 'pc.id', '=', 'pum.prospecting_candidate_id')
            ->where('pum.unit_id', $context->unit_id)
            ->whereIn('pc.status', ['pending_resolution', 'probable_existing_review'])->exists();
        $productMatchReviewed = DB::table('unit_product_matches')->where('unit_business_context_id', $context->id)
            ->whereIn('status', [UnitProductMatchStatus::Reviewed->value, UnitProductMatchStatus::Approved->value])->exists();
        $reviewParts = [! empty($productScores), $productMatchReviewed, $sources->isNotEmpty(), $verifiedChannel];
        $reviewCompleteness = count(array_filter($reviewParts)) * 25;
        $evidence = $snapshotRows->take(20)->map(static fn ($row): array => [
            'factor_code' => 'top_product_relevance',
            'type' => 'product_relevance_snapshot',
            'reference' => 'product-relevance-snapshot:'.$row->id,
            'hash' => hash('sha256', 'product-relevance-snapshot:'.$row->id),
            'confidence' => (int) $row->confidence,
            'verified' => $row->review_status === 'reviewed',
            'at' => (string) $row->created_at,
        ])->all();

        return new ScoringInput('prospect_priority', [
            'unit_business_context_id' => (int) $context->id,
            'unit_id' => (int) $context->unit_id,
        ], [
            'lane' => $context->lane->value,
            'role_code' => $context->role_code->value,
            'product_scores' => $productScores,
            'verified_public_channel' => $verifiedChannel,
            'geography_fit' => $geographyEvidence,
            'freshness_percent' => $freshness,
            'same_lane_transaction_count' => min(1000000, $this->transactions->transactionCount($actor, $context)),
            'review_completeness_percent' => $reviewCompleteness,
            'source_count' => $sources->count(),
            'unresolved_duplicate' => $unresolvedDuplicate,
            'stale_dossier' => $sources->isNotEmpty() && $freshness === 0,
            'do_not_contact' => $doNotContact,
            'suppressed' => $suppressed,
            'policy_blocked' => ! in_array($context->lane, [BusinessLane::Sales, BusinessLane::Procurement], true)
                || $context->status->value !== 'active',
        ], $evidence);
    }
}
