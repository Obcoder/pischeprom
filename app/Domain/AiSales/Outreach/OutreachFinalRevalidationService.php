<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Outreach\Enums\OutreachDlpStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachDraftStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachRevalidationCheckpoint;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Services\AiKillSwitchService;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachDispatch;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftReview;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

final class OutreachFinalRevalidationService
{
    public function __construct(
        private readonly OutreachAuthorizationService $authorization,
        private readonly CommunicationPermissionService $permissions,
        private readonly CommunicationSuppressionService $suppressions,
        private readonly AiKillSwitchService $killSwitches,
    ) {}

    public function evaluate(
        OutreachDraft $draft,
        User $actor,
        OutreachRevalidationCheckpoint $checkpoint,
        ?OutreachDispatch $dispatch = null,
    ): OutreachFinalRevalidationResult {
        $draft->loadMissing([
            'unit', 'businessContext', 'contactLink.email', 'productMatch', 'goodMatch',
            'revisions.claims', 'reviews', 'productRelevanceSnapshot', 'goodFitSnapshot',
            'prospectPrioritySnapshot',
        ]);

        $reasons = [];
        $context = $draft->businessContext;
        $revision = $draft->currentRevision();
        $requiredPermission = match ($checkpoint) {
            OutreachRevalidationCheckpoint::Prepare => OutreachAuthorizationService::PREPARE_DISPATCH,
            OutreachRevalidationCheckpoint::Queue, OutreachRevalidationCheckpoint::Worker => OutreachAuthorizationService::QUEUE_DISPATCH,
            OutreachRevalidationCheckpoint::EligibilityPreview => OutreachAuthorizationService::VIEW_DISPATCH,
        };

        if (! $context || ! $this->authorization->can($actor, $requiredPermission, $context)) {
            $reasons[] = 'actor_permission_missing';
        }
        if (! $context || (int) $context->unit_id !== (int) $draft->unit_id) {
            $reasons[] = 'context_unit_mismatch';
        } elseif ($context->lane !== BusinessLane::Sales) {
            $reasons[] = 'context_lane_not_sales';
        } elseif (! in_array($context->role_code, [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer], true)) {
            $reasons[] = 'context_role_not_customer';
        }
        if ($context?->status !== UnitContextStatus::Active || $context?->archived_at) {
            $reasons[] = 'context_not_active';
        }

        if ($draft->status !== OutreachDraftStatus::Approved) {
            $reasons[] = 'draft_not_approved';
        }
        if ($draft->expires_at && $draft->expires_at->isPast()) {
            $reasons[] = 'draft_expired';
        }
        if (! $revision) {
            $reasons[] = 'current_revision_missing';
        } elseif ($revision->dlp_status !== OutreachDlpStatus::Passed) {
            $reasons[] = 'dlp_not_passed';
        }

        if ($revision) {
            $latestReviews = $draft->reviews
                ->where('outreach_draft_revision_id', $revision->id)
                ->sortByDesc('id')
                ->unique(fn (OutreachDraftReview $review): string => $review->review_type->value);
            foreach (OutreachReviewType::cases() as $type) {
                $approved = $latestReviews
                    ->first(fn (OutreachDraftReview $review): bool => $review->review_type === $type)
                    ?->decision === OutreachReviewDecision::Approved;
                if (! $approved) {
                    $reasons[] = $type->value.'_review_not_current';
                }
            }
            if ($revision->claims->contains(fn ($claim): bool => $claim->fresh_until?->isPast() === true)) {
                $reasons[] = 'claim_evidence_stale';
            }
        }

        $contact = $draft->contactLink;
        if (! $contact || ! $contact->email || ! $draft->email_id) {
            $reasons[] = 'recipient_unresolved';
        } elseif ((int) $contact->unit_id !== (int) $draft->unit_id
            || (int) $contact->unit_business_context_id !== (int) $draft->unit_business_context_id
            || (int) $contact->email_id !== (int) $draft->email_id
            || $contact->archived_at
            || ! $contact->email->is_active) {
            $reasons[] = 'recipient_scope_changed';
        }

        $productMatch = $draft->productMatch;
        if (! $productMatch
            || (int) $productMatch->unit_id !== (int) $draft->unit_id
            || (int) $productMatch->unit_business_context_id !== (int) $draft->unit_business_context_id) {
            $reasons[] = 'product_match_scope_changed';
        } elseif ($productMatch->status !== UnitProductMatchStatus::Approved
            || ($productMatch->stale_after && $productMatch->stale_after->isPast())) {
            $reasons[] = 'product_match_not_current';
        }
        if ($draft->goodMatch) {
            if ((int) $draft->goodMatch->unit_id !== (int) $draft->unit_id
                || (int) $draft->goodMatch->unit_business_context_id !== (int) $draft->unit_business_context_id
                || (int) $draft->goodMatch->unit_product_match_id !== (int) $draft->unit_product_match_id) {
                $reasons[] = 'good_match_scope_changed';
            } elseif ($draft->goodMatch->status !== UnitGoodMatchStatus::Approved
                || ! in_array($draft->goodMatch->fit_status, [GoodOfferFitStatus::ApprovedForOffer, GoodOfferFitStatus::PreferredOffer], true)
                || ($draft->goodMatch->stale_after && $draft->goodMatch->stale_after->isPast())) {
                $reasons[] = 'good_match_not_current';
            }
        }
        foreach (['productRelevanceSnapshot', 'goodFitSnapshot', 'prospectPrioritySnapshot'] as $relation) {
            $snapshot = $draft->{$relation};
            if ($snapshot && ((int) $snapshot->unit_id !== (int) $draft->unit_id
                || (int) $snapshot->unit_business_context_id !== (int) $draft->unit_business_context_id)) {
                $reasons[] = 'score_snapshot_scope_changed';
            }
            if ($snapshot && ($snapshot->stale_at || $snapshot->superseded_at)) {
                $reasons[] = 'score_snapshot_stale';
            }
            if ($snapshot && Str::startsWith((string) $snapshot->eligibility, 'blocked_')) {
                $reasons[] = 'scoring_eligibility_blocked';
            }
        }

        $permission = null;
        if ($contact?->email && $draft->email_id) {
            $permission = $this->permissions->activePermissionFor(
                $draft->unit_business_context_id,
                $draft->unit_contact_context_link_id,
                $draft->productMatch->product_id,
                $draft->purpose,
            );
            if (! $permission) {
                $reasons[] = 'scoped_permission_missing';
            } elseif ((int) $permission->unit_id !== (int) $draft->unit_id
                || (int) $permission->unit_business_context_id !== (int) $draft->unit_business_context_id
                || (int) $permission->unit_contact_context_link_id !== (int) $draft->unit_contact_context_link_id
                || (int) $permission->email_id !== (int) $draft->email_id
                || $permission->channel !== 'email'
                || ! hash_equals(
                    (string) $permission->endpoint_hash,
                    $this->permissions->endpointHash($contact->email->address),
                )) {
                $reasons[] = 'permission_scope_invalid';
            }
            $reasons = [...$reasons, ...$this->suppressions->blockReasons($draft->unit, $context, $contact)];
        }

        $revisionHash = $this->revisionHash($revision);
        $permissionScopeHash = $this->permissionScopeHash($permission);
        $senderConfigHash = $this->senderConfigHash($reasons);

        if ($dispatch) {
            if ((int) $dispatch->unit_id !== (int) $draft->unit_id
                || (int) $dispatch->unit_business_context_id !== (int) $draft->unit_business_context_id
                || (int) $dispatch->unit_contact_context_link_id !== (int) $draft->unit_contact_context_link_id
                || (int) $dispatch->unit_product_match_id !== (int) $draft->unit_product_match_id
                || (int) ($dispatch->unit_good_match_id ?? 0) !== (int) ($draft->unit_good_match_id ?? 0)
                || $dispatch->purpose !== $draft->purpose) {
                $reasons[] = 'dispatch_scope_or_purpose_changed';
            }
            if ((int) $dispatch->outreach_draft_revision_id !== (int) $revision?->id
                || ! hash_equals($dispatch->revision_hash, $revisionHash)
                || ! hash_equals($dispatch->renderer_hash, (string) $revision?->renderer_hash)
                || ! hash_equals($dispatch->dlp_hash, (string) $revision?->dlp_hash)
                || ! hash_equals($dispatch->evidence_hash, (string) $draft->evidence_hash)) {
                $reasons[] = 'revision_or_evidence_changed';
            }
            if (! $permission
                || (int) $dispatch->communication_permission_id !== (int) $permission->id
                || ! hash_equals($dispatch->permission_scope_hash, $permissionScopeHash)) {
                $reasons[] = 'permission_scope_changed';
            }
            if (! hash_equals($dispatch->sender_config_hash, $senderConfigHash)) {
                $reasons[] = 'sender_configuration_changed';
            }
            if ($dispatch->request_profile !== 'outreach_zero_retry') {
                $reasons[] = 'zero_retry_profile_missing';
            }
            if (preg_match('/\A[a-f0-9]{64}\z/', (string) $dispatch->unsubscribe_token_hash) !== 1) {
                $reasons[] = 'unsubscribe_token_missing';
            }
        }
        if (! Route::has('mailings.unsubscribe.show') || ! Route::has('mailings.unsubscribe.submit')) {
            $reasons[] = 'unsubscribe_route_missing';
        }

        if (in_array($checkpoint, [OutreachRevalidationCheckpoint::Queue, OutreachRevalidationCheckpoint::Worker], true)) {
            $this->appendRuntimeReasons($reasons, $draft, $dispatch);
        }

        $reasons = array_values(array_unique($reasons));
        sort($reasons);
        $decisionHash = AiCanonicalJson::hash([
            'checkpoint' => $checkpoint->value,
            'draft_public_id' => $draft->public_id,
            'dispatch_public_id' => $dispatch?->public_id,
            'revision_hash' => $revisionHash,
            'permission_scope_hash' => $permissionScopeHash,
            'sender_config_hash' => $senderConfigHash,
            'reasons' => $reasons,
            'policy' => config('ai-sales.outreach.dispatch_policy_version', 'stage13-v1'),
        ]);

        return new OutreachFinalRevalidationResult(
            eligible: $reasons === [],
            blockReasons: $reasons,
            permissionId: $permission?->id,
            decisionHash: $decisionHash,
            revisionHash: $revisionHash,
            permissionScopeHash: $permissionScopeHash,
            senderConfigHash: $senderConfigHash,
        );
    }

    private function revisionHash(mixed $revision): string
    {
        return AiCanonicalJson::hash([
            'public_id' => $revision?->public_id,
            'number' => $revision?->revision_number,
            'subject_hash' => $revision ? hash('sha256', (string) $revision->subject) : null,
            'plaintext_hash' => $revision ? hash('sha256', (string) $revision->plaintext) : null,
            'html_hash' => $revision ? hash('sha256', (string) $revision->html) : null,
            'renderer_hash' => $revision?->renderer_hash,
            'dlp_hash' => $revision?->dlp_hash,
            'claim_set_hash' => $revision?->claim_set_hash,
            'input_hash' => $revision?->input_hash,
        ]);
    }

    private function permissionScopeHash(mixed $permission): string
    {
        return AiCanonicalJson::hash([
            'public_id' => $permission?->public_id,
            'unit_id' => $permission?->unit_id,
            'context_id' => $permission?->unit_business_context_id,
            'contact_id' => $permission?->unit_contact_context_link_id,
            'email_id' => $permission?->email_id,
            'sender_scope' => $permission?->sender_scope,
            'purpose' => $permission?->purpose?->value,
            'product_id' => $permission?->product_id,
            'status' => $permission?->status?->value,
            'valid_from' => $permission?->valid_from?->toISOString(),
            'valid_until' => $permission?->valid_until?->toISOString(),
            'evidence_set_hash' => $permission?->evidence_set_hash,
            'lock_version' => $permission?->lock_version,
        ]);
    }

    /** @param list<string> $reasons */
    private function senderConfigHash(array &$reasons): string
    {
        $from = trim((string) config('services.unisender_go.from_email'));
        $replyTo = trim((string) config('services.unisender_go.reply_to'));
        if (! filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
            $reasons[] = 'server_from_invalid';
        }
        if (! filter_var($replyTo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $replyTo)) {
            $reasons[] = 'server_reply_to_invalid';
        }

        return AiCanonicalJson::hash([
            'from' => Str::lower($from),
            'reply_to' => Str::lower($replyTo),
            'profile' => 'outreach_zero_retry',
        ]);
    }

    /** @param list<string> $reasons */
    private function appendRuntimeReasons(array &$reasons, OutreachDraft $draft, ?OutreachDispatch $dispatch): void
    {
        foreach ([
            'dispatch_pipeline_enabled' => 'dispatch_pipeline_disabled',
            'dispatch_enabled' => 'outreach_dispatch_disabled',
            'queue_enabled' => 'outreach_queue_disabled',
            'provider_send_enabled' => 'outreach_provider_send_disabled',
        ] as $flag => $reason) {
            if (! config('ai-sales.outreach.'.$flag, false)) {
                $reasons[] = $reason;
            }
        }
        if (! config('ai-sales.enabled', false) || ! config('ai-sales.outreach_sending_enabled', false)) {
            $reasons[] = 'global_outreach_sending_disabled';
        }
        if (! config('services.unisender_go.enabled', false)) {
            $reasons[] = 'unisender_provider_disabled';
        }
        if ((int) config('ai-sales.outreach.limits.provider_retries', 0) !== 0) {
            $reasons[] = 'provider_retries_not_zero';
        }
        if ((bool) config('ai-sales.outreach.limits.provider_failover', false)
            || (bool) config('ai-sales.provider_failover_enabled', false)) {
            $reasons[] = 'provider_failover_enabled';
        }
        try {
            if ($this->killSwitches->all()['global'] ?? true) {
                $reasons[] = 'global_kill_switch_active';
            }
        } catch (Throwable) {
            $reasons[] = 'global_kill_switch_unavailable';
        }

        $globalLimit = (int) config('ai-sales.outreach.limits.global_daily_sends', 0);
        $domainLimit = (int) config('ai-sales.outreach.limits.per_domain_daily_sends', 0);
        if ($globalLimit < 1) {
            $reasons[] = 'global_daily_limit_zero';
        } elseif (OutreachDispatch::query()->whereDate('provider_accepted_at', today())->count() >= $globalLimit) {
            $reasons[] = 'global_daily_limit_reached';
        }
        if ($domainLimit < 1) {
            $reasons[] = 'domain_daily_limit_zero';
        } elseif ($draft->contactLink?->email) {
            $domain = Str::after(Str::lower($draft->contactLink->email->address), '@');
            $count = OutreachDispatch::query()
                ->join('unit_contact_context_links', 'unit_contact_context_links.id', '=', 'outreach_dispatches.unit_contact_context_link_id')
                ->join('emails', 'emails.id', '=', 'unit_contact_context_links.email_id')
                ->whereDate('outreach_dispatches.provider_accepted_at', today())
                ->where('emails.address', 'like', '%@'.$domain)
                ->count();
            if ($count >= $domainLimit) {
                $reasons[] = 'domain_daily_limit_reached';
            }
        }

        $cooldownHours = (int) config('ai-sales.outreach.limits.recipient_cooldown_hours', 24);
        if ($dispatch && $cooldownHours > 0 && OutreachDispatch::query()
            ->where('unit_contact_context_link_id', $dispatch->unit_contact_context_link_id)
            ->where('id', '!=', $dispatch->id)
            ->where('provider_accepted_at', '>=', now()->subHours($cooldownHours))
            ->exists()) {
            $reasons[] = 'recipient_cooldown_active';
        }
    }
}
