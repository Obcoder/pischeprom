<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionAutomationMode;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ScoreEligibility;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\ClientAcquisitionCampaign;
use App\Models\CommunicationSuppression;
use App\Models\OutreachDraft;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitProductMatch;

final class AutonomousOutreachDraftPolicy
{
    public const CODE = 'autonomous_outreach_draft.v1';

    public const VERSION = '1';

    public function __construct(
        private readonly ClientAcquisitionCampaignFeatureGuard $features,
        private readonly ClientAcquisitionCampaignHashes $hashes,
    ) {}

    /** @return array{product_snapshot_id: int, priority_snapshot_id: int} */
    public function assertEligible(
        ClientAcquisitionCampaign $campaign,
        UnitBusinessContext $context,
        UnitProductMatch $match,
    ): array {
        $this->features->autoDraft();
        if ($campaign->automation_mode !== ClientAcquisitionAutomationMode::AutonomousReviewed
            || ! $campaign->auto_draft_approved
            || $campaign->auto_draft_policy_code !== self::CODE
            || $campaign->auto_draft_policy_version !== self::VERSION
            || ! $this->hashes->isCurrent($campaign)) {
            throw new PolicyViolation('auto_draft_campaign_policy_blocked', 'Auto draft lacks a current explicit campaign approval.');
        }
        if ($context->lane !== BusinessLane::Sales
            || $context->role_code !== UnitRoleCode::ProspectiveCustomer
            || $context->status->value !== 'active'
            || (int) $context->unit_id !== (int) $match->unit_id
            || (int) $context->id !== (int) $match->unit_business_context_id
            || $match->status !== UnitProductMatchStatus::Approved
            || ($match->stale_after && $match->stale_after->isPast())) {
            throw new PolicyViolation('auto_draft_context_or_match_blocked', 'A current approved sales Product match is required.');
        }
        $candidateId = $match->prospecting_candidate_product_id
            ? $match->candidateProduct()->value('prospecting_candidate_id') : null;
        $candidate = $candidateId ? \App\Models\ProspectingCandidate::query()->find($candidateId) : null;
        if (! $candidate
            || (int) $candidate->resolved_unit_id !== (int) $context->unit_id
            || ! in_array($candidate->status->value, ['exact_existing_unit', 'existing_unit_enriched', 'new_unit_created'], true)
            || ! $campaign->runLinks()->where('prospecting_search_job_id', $candidate->prospecting_search_job_id)->exists()) {
            throw new PolicyViolation('auto_draft_campaign_binding_blocked', 'Product match is outside this campaign or unresolved.');
        }
        $productSnapshot = $match->relevanceSnapshots()->whereNull('stale_at')->whereNull('superseded_at')->latest('id')->first();
        $prioritySnapshot = $context->prospectPrioritySnapshots()->whereNull('stale_at')->whereNull('superseded_at')->latest('id')->first();
        $minimumRelevance = (int) config('ai-sales.campaigns.policies.auto_draft.minimum_product_relevance', 60);
        $minimumConfidence = (int) config('ai-sales.campaigns.policies.auto_draft.minimum_confidence', 70);
        $minimumPriority = (int) config('ai-sales.campaigns.policies.auto_draft.minimum_prospect_priority', 50);
        if (! $productSnapshot || ! $prioritySnapshot
            || $productSnapshot->effective_score < $minimumRelevance
            || $productSnapshot->confidence < $minimumConfidence
            || $prioritySnapshot->effective_score < $minimumPriority
            || $prioritySnapshot->confidence < $minimumConfidence
            || ScoreEligibility::from($productSnapshot->eligibility)->blocked()
            || ScoreEligibility::from($prioritySnapshot->eligibility)->blocked()) {
            throw new PolicyViolation('auto_draft_score_threshold_blocked', 'Current score and confidence thresholds are not met.');
        }
        if (\App\Models\ProspectingCandidateUnitMatch::query()
            ->where('unit_id', $context->unit_id)
            ->whereHas('candidate', fn ($query) => $query->whereIn('status', ['pending_resolution', 'probable_existing_review']))
            ->exists()) {
            throw new PolicyViolation('auto_draft_duplicate_blocked', 'An unresolved duplicate blocks automatic drafting.');
        }
        if (! UnitContactContextLink::query()
            ->where('unit_id', $context->unit_id)
            ->where('unit_business_context_id', $context->id)
            ->whereNull('archived_at')
            ->where('contact_role', 'business_general')
            ->where('verification_status', 'verified')
            ->whereIn('data_classification', ['public', 'personal_data'])
            ->whereIn('visibility_scope', ['shared_public', 'sales_lane'])
            ->exists()) {
            throw new PolicyViolation('auto_draft_corporate_channel_required', 'A verified corporate channel must exist locally.');
        }
        $now = now();
        if (CommunicationSuppression::query()->whereNull('cleared_at')->where('active_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>', $now))
            ->where(function ($query) use ($context): void {
                $query->where('scope', 'global')
                    ->orWhere(fn ($nested) => $nested->where('scope', 'unit')->where('unit_id', $context->unit_id))
                    ->orWhere(fn ($nested) => $nested->where('scope', 'context')->where('unit_business_context_id', $context->id));
            })->exists()) {
            throw new PolicyViolation('auto_draft_suppression_blocked', 'An active suppression blocks automatic drafting.');
        }
        $campaignDraftQuery = OutreachDraft::query()->whereHas('productMatch.candidateProduct.candidate.job', function ($query) use ($campaign): void {
            $query->whereIn('id', $campaign->runLinks()->whereNotNull('prospecting_search_job_id')->pluck('prospecting_search_job_id'));
        });
        $runDrafts = OutreachDraft::query()->whereHas('productMatch.candidateProduct.candidate', fn ($query) => $query
            ->where('prospecting_search_job_id', $candidate->prospecting_search_job_id))->count();
        $daily = (clone $campaignDraftQuery)->where('outreach_drafts.created_at', '>=', now()->startOfDay())->count();
        $monthly = (clone $campaignDraftQuery)->where('outreach_drafts.created_at', '>=', now()->startOfMonth())->count();
        $globalDaily = OutreachDraft::query()->where('created_at', '>=', now()->startOfDay())->count();
        $globalMonthly = OutreachDraft::query()->where('created_at', '>=', now()->startOfMonth())->count();
        if ($campaign->max_drafts_per_run < 1 || $campaign->max_drafts_per_day < 1 || $campaign->max_drafts_per_month < 1
            || $runDrafts >= $campaign->max_drafts_per_run
            || $daily >= $campaign->max_drafts_per_day || $monthly >= $campaign->max_drafts_per_month
            || $globalDaily >= (int) config('ai-sales.campaigns.limits.global_drafts_per_day', 0)
            || $globalMonthly >= (int) config('ai-sales.campaigns.limits.global_drafts_per_month', 0)) {
            throw new PolicyViolation('auto_draft_cap_exhausted', 'Auto draft campaign or global cap is exhausted.');
        }

        return ['product_snapshot_id' => $productSnapshot->id, 'priority_snapshot_id' => $prioritySnapshot->id];
    }
}
