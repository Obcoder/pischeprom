<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Enums\OutreachDraftStatus;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Domain\AiSales\Outreach\OutreachSafeDto;
use App\Models\ClientAcquisitionCampaign;
use App\Models\OutreachDraft;
use App\Models\UnitBusinessContext;
use App\Models\UnitProductMatch;
use App\Models\User;

final class AutonomousOutreachDraftService
{
    public function __construct(
        private readonly ClientAcquisitionCampaignAuthorizationService $authorization,
        private readonly AutonomousOutreachDraftPolicy $policy,
        private readonly OutreachDraftService $drafts,
    ) {}

    public function create(
        ClientAcquisitionCampaign $campaign,
        UnitBusinessContext $context,
        UnitProductMatch $match,
        User $actor,
    ): OutreachDraft {
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::OPERATE);
        $scores = $this->policy->assertEligible($campaign, $context, $match);
        $existing = OutreachDraft::query()
            ->where('unit_business_context_id', $context->id)
            ->where('unit_product_match_id', $match->id)
            ->whereIn('status', ['draft', 'review_required', 'approved'])
            ->latest('id')->first();
        if ($existing) {
            return $existing;
        }
        $draft = $this->drafts->create($actor, $context->unit, $context, [
            'unit_product_match_id' => $match->id,
            'product_relevance_snapshot_id' => $scores['product_snapshot_id'],
            'prospect_priority_snapshot_id' => $scores['priority_snapshot_id'],
            'purpose' => 'advertising_outreach',
        ]);
        $revision = $this->drafts->generate($draft, $actor);
        $dto = OutreachSafeDto::fromDraft($draft->fresh([
            'unit', 'businessContext', 'productMatch.product', 'productRelevanceSnapshot', 'prospectPrioritySnapshot',
        ]))->toArray();
        if (array_key_exists('recipient', $dto) || array_key_exists('email', $dto)
            || array_key_exists('contact', $dto) || $draft->email_id !== null || $draft->unit_contact_context_link_id !== null) {
            throw new PolicyViolation('auto_draft_recipient_data_blocked', 'Recipient data entered the automatic draft path.');
        }
        $draft = $draft->fresh();
        if ($draft->status !== OutreachDraftStatus::ReviewRequired || $revision->claims()->count() < 1
            || $draft->reviews()->exists() || $draft->dispatches()->exists()) {
            throw new PolicyViolation('auto_draft_review_boundary_blocked', 'Automatic draft exceeded the review-only boundary.');
        }

        return $draft;
    }
}
