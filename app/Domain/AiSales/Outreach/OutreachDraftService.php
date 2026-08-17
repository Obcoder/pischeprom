<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use App\Domain\AiSales\Outreach\Enums\OutreachDlpStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachDraftStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachGenerationOrigin;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftClaim;
use App\Models\OutreachDraftReview;
use App\Models\OutreachDraftRevision;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutreachDraftService
{
    public function __construct(
        private readonly OutreachAuthorizationService $authorization,
        private readonly OutreachFeatureGuard $features,
        private readonly FakeStructuredOutreachProvider $provider,
        private readonly OutreachStructuredContentValidator $schema,
        private readonly CodeOwnedOutreachRenderer $renderer,
        private readonly DeterministicOutreachDlpGuard $dlp,
    ) {}

    public function create(User $actor, Unit $unit, UnitBusinessContext $context, array $data): OutreachDraft
    {
        $this->features->drafts();
        $this->authorization->authorize($actor, OutreachAuthorizationService::DRAFT, $unit, $context);
        $purpose = MessagePurpose::from($data['purpose'] ?? MessagePurpose::AdvertisingOutreach->value);
        if ($purpose !== MessagePurpose::AdvertisingOutreach) {
            throw new PolicyViolation('outreach_purpose_not_supported', 'Stage 12 creates advertising outreach drafts only.');
        }

        $productMatch = UnitProductMatch::query()->with('product')->findOrFail($data['unit_product_match_id']);
        $this->assertProductMatch($unit, $context, $productMatch);
        $goodMatch = isset($data['unit_good_match_id'])
            ? UnitGoodMatch::query()->with('good')->findOrFail($data['unit_good_match_id'])
            : null;
        if ($goodMatch) {
            $this->assertGoodMatch($unit, $context, $productMatch, $goodMatch);
        }

        $contact = isset($data['unit_contact_context_link_id'])
            ? UnitContactContextLink::query()->with('email')->findOrFail($data['unit_contact_context_link_id'])
            : null;
        if ($contact) {
            $this->assertContact($unit, $context, $contact);
        }

        $profile = (string) config('ai-sales.outreach.template_profile', 'product-first-corporate-v1');
        $version = (string) config('ai-sales.outreach.template_version', '1');
        $templateHash = AiCanonicalJson::hash(['profile' => $profile, 'version' => $version, 'renderer' => config('ai-sales.outreach.renderer_version')]);
        $policyHash = AiCanonicalJson::hash([
            'version' => config('ai-sales.outreach.policy_version'), 'purpose' => $purpose->value,
            'lane' => BusinessLane::Sales->value, 'dispatch' => false,
        ]);
        $input = [
            'unit_id' => $unit->id, 'context_id' => $context->id, 'contact_id' => $contact?->id,
            'product_match_id' => $productMatch->id, 'good_match_id' => $goodMatch?->id,
            'purpose' => $purpose->value, 'template_hash' => $templateHash,
        ];
        $evidence = [
            'product_match_hash' => $productMatch->evidence_hash,
            'good_match_hash' => $goodMatch?->evidence_hash,
            'product_relevance_snapshot_id' => $data['product_relevance_snapshot_id'] ?? null,
            'good_fit_snapshot_id' => $data['good_fit_snapshot_id'] ?? null,
            'prospect_priority_snapshot_id' => $data['prospect_priority_snapshot_id'] ?? null,
        ];

        return OutreachDraft::query()->create([
            'public_id' => (string) Str::uuid(), 'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id, 'unit_contact_context_link_id' => $contact?->id,
            'email_id' => $contact?->email_id, 'unit_product_match_id' => $productMatch->id,
            'unit_good_match_id' => $goodMatch?->id,
            'product_relevance_snapshot_id' => $this->validatedSnapshotId($data['product_relevance_snapshot_id'] ?? null, $productMatch->relevanceSnapshots(), $unit, $context),
            'good_fit_snapshot_id' => $goodMatch ? $this->validatedSnapshotId($data['good_fit_snapshot_id'] ?? null, $goodMatch->fitSnapshots(), $unit, $context) : null,
            'prospect_priority_snapshot_id' => $this->validatedSnapshotId($data['prospect_priority_snapshot_id'] ?? null, $context->prospectPrioritySnapshots(), $unit, $context),
            'purpose' => $purpose, 'status' => OutreachDraftStatus::Draft,
            'generation_origin' => OutreachGenerationOrigin::Manual, 'template_profile' => $profile,
            'template_version' => $version, 'template_hash' => $templateHash, 'policy_hash' => $policyHash,
            'input_hash' => AiCanonicalJson::hash($input), 'evidence_hash' => AiCanonicalJson::hash($evidence),
            'expires_at' => now()->addDays(14), 'created_by' => $actor->id,
        ]);
    }

    public function generate(OutreachDraft $draft, User $actor): OutreachDraftRevision
    {
        $this->features->fakeGeneration();
        $this->loadDraft($draft);
        $this->authorization->authorize($actor, OutreachAuthorizationService::DRAFT, $draft->unit, $draft->businessContext);
        $this->assertDraftEvidenceCurrent($draft);
        $dto = OutreachSafeDto::fromDraft($draft);
        $content = $this->provider->generate($dto);

        return $this->appendRevision($draft, $actor, $content, OutreachGenerationOrigin::FakeStructured, $dto->hash());
    }

    public function revise(OutreachDraft $draft, User $actor, array $content): OutreachDraftRevision
    {
        $this->features->drafts();
        $this->loadDraft($draft);
        $this->authorization->authorize($actor, OutreachAuthorizationService::DRAFT, $draft->unit, $draft->businessContext);
        $this->assertDraftEvidenceCurrent($draft);

        return $this->appendRevision(
            $draft, $actor, $content, OutreachGenerationOrigin::HumanEdit,
            AiCanonicalJson::hash(['draft_input' => $draft->input_hash, 'content' => $content]),
        );
    }

    public function review(
        OutreachDraft $draft,
        OutreachDraftRevision $revision,
        User $actor,
        OutreachReviewType $type,
        OutreachReviewDecision $decision,
        string $reasonCode,
        ?string $safeNote,
    ): OutreachDraftReview {
        $this->features->drafts();
        $this->loadDraft($draft);
        $permission = $type === OutreachReviewType::Claims
            ? OutreachAuthorizationService::REVIEW_CLAIMS
            : OutreachAuthorizationService::REVIEW;
        $this->authorization->authorize($actor, $permission, $draft->unit, $draft->businessContext);
        if ((int) $revision->outreach_draft_id !== (int) $draft->id
            || (int) $revision->revision_number !== (int) $draft->current_revision_number) {
            throw new PolicyViolation('outreach_review_stale_revision', 'Only the current draft revision can be reviewed.');
        }
        if ($decision === OutreachReviewDecision::Approved && $revision->dlp_status !== OutreachDlpStatus::Passed) {
            throw new PolicyViolation('outreach_review_dlp_blocked', 'A DLP-blocked revision cannot be approved.');
        }

        $sequence = $draft->reviews()->count() + 1;
        $review = OutreachDraftReview::query()->create([
            'outreach_draft_id' => $draft->id, 'outreach_draft_revision_id' => $revision->id,
            'review_type' => $type, 'decision' => $decision, 'reason_code' => $reasonCode,
            'safe_note' => $safeNote,
            'decision_hash' => AiCanonicalJson::hash([
                'draft_public_id' => $draft->public_id, 'revision_public_id' => $revision->public_id,
                'type' => $type->value, 'decision' => $decision->value, 'reason' => $reasonCode,
                'actor_id' => $actor->id, 'sequence' => $sequence,
            ]),
            'reviewed_by' => $actor->id, 'reviewed_at' => now(),
        ]);

        $latest = $draft->reviews()->where('outreach_draft_revision_id', $revision->id)
            ->orderByDesc('id')->get()->unique(fn (OutreachDraftReview $item) => $item->review_type->value);
        $allApproved = collect(OutreachReviewType::cases())->every(fn (OutreachReviewType $required) => $latest->first(fn (OutreachDraftReview $item) => $item->review_type === $required)?->decision === OutreachReviewDecision::Approved
        );
        $draft->forceFill([
            'status' => $allApproved ? OutreachDraftStatus::Approved : OutreachDraftStatus::ReviewRequired,
            'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'lock_version' => $draft->lock_version + 1,
        ])->save();

        return $review;
    }

    private function appendRevision(OutreachDraft $draft, User $actor, array $content, OutreachGenerationOrigin $origin, string $inputHash): OutreachDraftRevision
    {
        if ($draft->current_revision_number >= (int) config('ai-sales.outreach.limits.revisions', 25)) {
            throw new PolicyViolation('outreach_revision_limit', 'Outreach revision limit reached.');
        }
        $content = $this->schema->validate($content);
        $this->validateClaimsAgainstEvidence($draft, $content['claims']);
        $rendered = $this->renderer->render($content);
        $dlp = $this->dlp->inspect($draft->businessContext, $content, $rendered);

        return DB::transaction(function () use ($draft, $actor, $content, $origin, $inputHash, $rendered, $dlp): OutreachDraftRevision {
            $parent = $draft->currentRevision();
            $number = $draft->current_revision_number + 1;
            $revision = OutreachDraftRevision::query()->create([
                'public_id' => (string) Str::uuid(), 'outreach_draft_id' => $draft->id,
                'parent_revision_id' => $parent?->id, 'revision_number' => $number, 'origin' => $origin,
                'structured_content' => $content, 'subject' => $rendered['subject'],
                'plaintext' => $rendered['plaintext'], 'html' => $rendered['html'],
                'renderer_version' => $rendered['renderer_version'], 'renderer_hash' => $rendered['renderer_hash'],
                'dlp_status' => $dlp->passed ? OutreachDlpStatus::Passed : OutreachDlpStatus::Blocked,
                'dlp_findings' => $dlp->codes, 'dlp_hash' => $dlp->hash(),
                'claim_set_hash' => AiCanonicalJson::hash(['claims' => $content['claims']]),
                'input_hash' => $inputHash, 'edited_by' => $actor->id,
            ]);
            foreach ($content['claims'] as $index => $claim) {
                OutreachDraftClaim::query()->create([
                    'outreach_draft_revision_id' => $revision->id, 'claim_type' => $claim['type'],
                    'text_fragment_hash' => hash('sha256', $claim['text']), 'evidence_type' => $claim['evidence_type'],
                    'evidence_reference' => $claim['evidence_reference'], 'evidence_hash' => $claim['evidence_hash'],
                    'evidence_status' => 'bound', 'confidence' => null, 'review_status' => 'pending',
                    'safe_rationale' => 'Claim is bound to a reviewed Unit match; separate claims review is required.',
                    'audit_hash' => AiCanonicalJson::hash([
                        'revision_public_id' => $revision->public_id, 'index' => $index,
                        'type' => $claim['type'], 'text_hash' => hash('sha256', $claim['text']),
                        'evidence_hash' => $claim['evidence_hash'],
                    ]),
                ]);
            }
            $draft->forceFill([
                'current_revision_number' => $number,
                'generation_origin' => $origin,
                'status' => $dlp->passed ? OutreachDraftStatus::ReviewRequired : OutreachDraftStatus::Blocked,
                'lock_version' => $draft->lock_version + 1, 'reviewed_by' => null, 'reviewed_at' => null,
            ])->save();

            return $revision->fresh('claims');
        });
    }

    private function loadDraft(OutreachDraft $draft): void
    {
        $draft->loadMissing([
            'unit', 'businessContext', 'contactLink.email', 'productMatch.product',
            'goodMatch.good', 'productRelevanceSnapshot', 'goodFitSnapshot', 'prospectPrioritySnapshot',
        ]);
    }

    private function assertProductMatch(Unit $unit, UnitBusinessContext $context, UnitProductMatch $match): void
    {
        if ((int) $match->unit_id !== (int) $unit->id || (int) $match->unit_business_context_id !== (int) $context->id
            || $match->status !== UnitProductMatchStatus::Approved || ($match->stale_after && $match->stale_after->isPast())) {
            throw new PolicyViolation('outreach_product_match_not_approved', 'An approved current Product match is required.');
        }
    }

    private function assertGoodMatch(Unit $unit, UnitBusinessContext $context, UnitProductMatch $product, UnitGoodMatch $good): void
    {
        if ((int) $good->unit_id !== (int) $unit->id || (int) $good->unit_business_context_id !== (int) $context->id
            || (int) $good->unit_product_match_id !== (int) $product->id
            || $good->status !== UnitGoodMatchStatus::Approved
            || ! in_array($good->fit_status, [GoodOfferFitStatus::ApprovedForOffer, GoodOfferFitStatus::PreferredOffer], true)
            || ($good->stale_after && $good->stale_after->isPast())) {
            throw new PolicyViolation('outreach_good_match_not_approved', 'Selected Good offer fit is not approved and current.');
        }
    }

    private function assertContact(Unit $unit, UnitBusinessContext $context, UnitContactContextLink $contact): void
    {
        if ((int) $contact->unit_id !== (int) $unit->id || (int) $contact->unit_business_context_id !== (int) $context->id
            || ! $contact->email_id || ! $contact->email || $contact->archived_at) {
            throw new PolicyViolation('outreach_contact_not_resolved', 'An existing Unit email contact link is required.');
        }
    }

    private function validatedSnapshotId(mixed $id, $relation, Unit $unit, UnitBusinessContext $context): ?int
    {
        if (! $id) {
            return null;
        }
        $snapshot = $relation->whereKey((int) $id)->first();
        if (! $snapshot || (int) $snapshot->unit_id !== (int) $unit->id
            || (int) $snapshot->unit_business_context_id !== (int) $context->id
            || $snapshot->stale_at || $snapshot->superseded_at) {
            throw new PolicyViolation('outreach_score_snapshot_stale', 'Selected scoring snapshot is not current.');
        }

        return (int) $snapshot->id;
    }

    private function assertDraftEvidenceCurrent(OutreachDraft $draft): void
    {
        $this->assertProductMatch($draft->unit, $draft->businessContext, $draft->productMatch);
        if ($draft->goodMatch) {
            $this->assertGoodMatch($draft->unit, $draft->businessContext, $draft->productMatch, $draft->goodMatch);
        }
        if ($draft->contactLink) {
            $this->assertContact($draft->unit, $draft->businessContext, $draft->contactLink);
        }
        if ($draft->expires_at && $draft->expires_at->isPast()) {
            throw new PolicyViolation('outreach_draft_expired', 'Outreach draft evidence has expired.');
        }
    }

    private function validateClaimsAgainstEvidence(OutreachDraft $draft, array $claims): void
    {
        foreach ($claims as $claim) {
            $valid = match ($claim['evidence_type']) {
                'unit_product_match' => hash_equals($draft->productMatch->evidence_hash, $claim['evidence_hash'])
                    && (string) $draft->productMatch->evidence_reference === $claim['evidence_reference'],
                'unit_good_match' => $draft->goodMatch
                    && hash_equals($draft->goodMatch->evidence_hash, $claim['evidence_hash'])
                    && (string) $draft->goodMatch->evidence_reference === $claim['evidence_reference'],
                default => false,
            };
            if (! $valid) {
                throw new PolicyViolation('outreach_claim_evidence_mismatch', 'Outreach claim is not bound to current reviewed evidence.');
            }
        }
    }
}
