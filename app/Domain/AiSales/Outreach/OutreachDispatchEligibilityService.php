<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachDlpStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachDraftStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachDispatchDecision;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftReview;
use App\Models\User;
use Illuminate\Support\Str;

class OutreachDispatchEligibilityService
{
    public function __construct(
        private readonly OutreachFeatureGuard $features,
        private readonly CommunicationPermissionService $permissions,
        private readonly CommunicationSuppressionService $suppressions,
    ) {}

    public function evaluate(OutreachDraft $draft, ?User $actor = null, bool $persist = false): OutreachDispatchEligibilityResult
    {
        $draft->loadMissing([
            'unit', 'businessContext', 'contactLink.email', 'productMatch', 'goodMatch',
            'revisions', 'reviews', 'productRelevanceSnapshot', 'goodFitSnapshot', 'prospectPrioritySnapshot',
        ]);
        $reasons = ['stage12_dispatch_not_implemented'];
        if (! $this->features->dispatchAllowed()) {
            $reasons[] = 'global_dispatch_disabled';
        }
        if (! config('ai-sales.outreach_sending_enabled', false)) {
            $reasons[] = 'ai_outreach_sending_disabled';
        }
        if (! config('ai-sales.outreach.dispatch_enabled', false)) {
            $reasons[] = 'outreach_dispatch_flag_disabled';
        }
        if (! config('ai-sales.outreach.auto_send_enabled', false)) {
            $reasons[] = 'auto_send_disabled';
        }
        if ($draft->status !== OutreachDraftStatus::Approved) {
            $reasons[] = 'draft_not_approved';
        }
        if ($draft->expires_at && $draft->expires_at->isPast()) {
            $reasons[] = 'draft_expired';
        }

        $revision = $draft->currentRevision();
        if (! $revision) {
            $reasons[] = 'current_revision_missing';
        } elseif ($revision->dlp_status !== OutreachDlpStatus::Passed) {
            $reasons[] = 'dlp_blocked';
        }
        if ($draft->productMatch->status !== UnitProductMatchStatus::Approved
            || ($draft->productMatch->stale_after && $draft->productMatch->stale_after->isPast())) {
            $reasons[] = 'product_match_not_current';
        }
        if ($draft->goodMatch && ($draft->goodMatch->status !== UnitGoodMatchStatus::Approved
            || ! in_array($draft->goodMatch->fit_status, [GoodOfferFitStatus::ApprovedForOffer, GoodOfferFitStatus::PreferredOffer], true)
            || ($draft->goodMatch->stale_after && $draft->goodMatch->stale_after->isPast()))) {
            $reasons[] = 'good_match_not_current';
        }
        foreach (['productRelevanceSnapshot', 'goodFitSnapshot', 'prospectPrioritySnapshot'] as $relation) {
            $snapshot = $draft->{$relation};
            if ($snapshot && ($snapshot->stale_at || $snapshot->superseded_at)) {
                $reasons[] = 'score_snapshot_stale';
            }
        }

        if ($revision) {
            $latestReviews = $draft->reviews->where('outreach_draft_revision_id', $revision->id)
                ->sortByDesc('id')->unique(fn (OutreachDraftReview $review) => $review->review_type->value);
            foreach (OutreachReviewType::cases() as $type) {
                if ($latestReviews->first(fn (OutreachDraftReview $review) => $review->review_type === $type)?->decision !== OutreachReviewDecision::Approved) {
                    $reasons[] = $type->value.'_review_missing';
                }
            }
        }

        $permission = null;
        if (! $draft->contactLink || ! $draft->email_id) {
            $reasons[] = 'recipient_unresolved';
        } else {
            $permission = $this->permissions->activePermissionFor(
                $draft->unit_business_context_id,
                $draft->unit_contact_context_link_id,
                $draft->productMatch->product_id,
                $draft->purpose,
            );
            if (! $permission) {
                $reasons[] = 'scoped_permission_missing';
            }
            $reasons = [...$reasons, ...$this->suppressions->blockReasons($draft->unit, $draft->businessContext, $draft->contactLink)];
        }

        $reasons = array_values(array_unique($reasons));
        $nonRuntimeBlocks = array_values(array_diff($reasons, [
            'stage12_dispatch_not_implemented', 'global_dispatch_disabled', 'ai_outreach_sending_disabled',
            'outreach_dispatch_flag_disabled', 'auto_send_disabled',
        ]));
        $result = new OutreachDispatchEligibilityResult(false, $nonRuntimeBlocks === [], $reasons, $permission?->id);

        if ($persist) {
            $payload = [
                'draft_public_id' => $draft->public_id, 'revision_public_id' => $revision?->public_id,
                'permission_public_id' => $permission?->public_id, 'eligible' => false,
                'block_reasons' => $reasons, 'policy_version' => config('ai-sales.outreach.policy_version'),
                'actor_id' => $actor?->id, 'sequence' => $draft->dispatchDecisions()->count() + 1,
            ];
            OutreachDispatchDecision::query()->create([
                'public_id' => (string) Str::uuid(), 'outreach_draft_id' => $draft->id,
                'outreach_draft_revision_id' => $revision?->id, 'communication_permission_id' => $permission?->id,
                'eligible' => false, 'block_reasons' => $reasons, 'decision_hash' => AiCanonicalJson::hash($payload),
                'policy_version' => (string) config('ai-sales.outreach.policy_version'),
                'evaluated_by' => $actor?->id, 'evaluated_at' => now(),
            ]);
        }

        return $result;
    }
}
