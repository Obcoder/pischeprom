<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Outreach\CommunicationPermissionService;
use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Domain\AiSales\Outreach\OutreachDispatchEligibilityService;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Domain\AiSales\Outreach\OutreachFeatureGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ReviewCommunicationPermissionRequest;
use App\Http\Requests\AiSales\ReviewOutreachDraftRequest;
use App\Http\Requests\AiSales\ReviseOutreachDraftRequest;
use App\Http\Requests\AiSales\RevokeCommunicationPermissionRequest;
use App\Http\Requests\AiSales\StoreCommunicationPermissionRequest;
use App\Http\Requests\AiSales\StoreOutreachDraftRequest;
use App\Models\CommunicationPermission;
use App\Models\CommunicationSuppression;
use App\Models\OutreachDispatch;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftRevision;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UnitOutreachController extends Controller
{
    public function index(
        Request $request,
        Unit $unit,
        OutreachFeatureGuard $features,
        OutreachAuthorizationService $authorization,
        OutreachDispatchEligibilityService $eligibility,
    ): JsonResponse {
        $features->view();
        Gate::authorize('view', $unit);
        $actor = $request->user();
        $unitContexts = UnitBusinessContext::query()->where('unit_id', $unit->id)->get();
        $contexts = $unitContexts
            ->filter(fn (UnitBusinessContext $context) => $authorization->can($actor, OutreachAuthorizationService::VIEW, $context));
        if ($unitContexts->contains(fn (UnitBusinessContext $context) => $context->lane === BusinessLane::Sales)
            && $contexts->isEmpty()) {
            abort(403, 'Outreach access is not authorized for this Unit sales context.');
        }
        $contextIdsFor = fn (string $permission) => $contexts
            ->filter(fn (UnitBusinessContext $context) => $authorization->can($actor, $permission, $context))
            ->pluck('id');
        $permissionContextIds = $contextIdsFor(OutreachAuthorizationService::VIEW_PERMISSIONS)
            ->merge($contextIdsFor(OutreachAuthorizationService::MANAGE_PERMISSIONS))
            ->unique()->values();
        $contactContextIds = $permissionContextIds
            ->merge($contextIdsFor(OutreachAuthorizationService::MANAGE_SUPPRESSIONS))
            ->unique()->values();

        $drafts = OutreachDraft::query()
            ->where('unit_id', $unit->id)->whereIn('unit_business_context_id', $contexts->pluck('id'))
            ->with(['businessContext', 'contactLink.email', 'productMatch.product', 'goodMatch.good', 'revisions.claims', 'reviews'])
            ->latest('id')->limit(100)->get();
        $dispatches = OutreachDispatch::query()
            ->where('unit_id', $unit->id)
            ->whereIn('unit_business_context_id', $contextIdsFor(OutreachAuthorizationService::VIEW_DISPATCH))
            ->with(['followUpPlan', 'replies'])
            ->latest('id')->limit(100)->get();
        $permissions = CommunicationPermission::query()
            ->where('unit_id', $unit->id)->whereIn('unit_business_context_id', $permissionContextIds)
            ->with(['contactLink.email', 'evidence'])->latest('id')->limit(100)->get();
        $suppressions = CommunicationSuppression::query()
            ->where('unit_id', $unit->id)->whereIn('unit_business_context_id', $contexts->pluck('id'))
            ->latest('id')->limit(100)->get();
        $productMatches = UnitProductMatch::query()->where('unit_id', $unit->id)
            ->whereIn('unit_business_context_id', $contexts->pluck('id'))->with('product')->latest('id')->get();
        $goodMatches = UnitGoodMatch::query()->where('unit_id', $unit->id)
            ->whereIn('unit_business_context_id', $contexts->pluck('id'))->with('good')->latest('id')->get();
        $contacts = UnitContactContextLink::query()->where('unit_id', $unit->id)
            ->whereIn('unit_business_context_id', $contexts->pluck('id'))->where('channel_type', 'email')
            ->whereNull('archived_at')->with('email')->get();

        return response()->json([
            'data' => [
                'contexts' => $contexts->map(fn (UnitBusinessContext $context) => [
                    'id' => $context->id, 'lane' => $context->lane->value, 'role_code' => $context->role_code->value,
                    'stage' => $context->stage->value, 'status' => $context->status->value,
                ])->values(),
                'drafts' => $drafts->map(fn (OutreachDraft $draft) => $this->draftPayload(
                    $draft,
                    $eligibility,
                    $contactContextIds->contains($draft->unit_business_context_id),
                )),
                'dispatches' => $dispatches->map(fn (OutreachDispatch $dispatch) => [
                    'id' => $dispatch->id,
                    'public_id' => $dispatch->public_id,
                    'draft_id' => $dispatch->outreach_draft_id,
                    'revision_id' => $dispatch->outreach_draft_revision_id,
                    'context_id' => $dispatch->unit_business_context_id,
                    'state' => $dispatch->state->value,
                    'request_profile' => $dispatch->request_profile,
                    'mail_message_id' => $dispatch->mail_message_id,
                    'sending_id' => $dispatch->sending_id,
                    'safe_summary' => $dispatch->safe_summary,
                    'last_block_reason' => $dispatch->last_block_reason,
                    'last_revalidated_at' => $dispatch->last_revalidated_at?->toISOString(),
                    'reply_count' => $dispatch->replies->count(),
                    'follow_up' => $dispatch->followUpPlan ? [
                        'status' => $dispatch->followUpPlan->status->value,
                        'max_follow_ups' => $dispatch->followUpPlan->max_follow_ups,
                        'recommendation_code' => $dispatch->followUpPlan->recommendation_code,
                        'cancellation_reason' => $dispatch->followUpPlan->cancellation_reason,
                    ] : null,
                ]),
                'permissions' => $permissions->map(fn (CommunicationPermission $permission) => $this->permissionPayload($permission)),
                'suppressions' => $suppressions->map(fn (CommunicationSuppression $suppression) => $this->suppressionPayload($suppression)),
                'product_matches' => $productMatches->map(fn (UnitProductMatch $match) => [
                    'id' => $match->id, 'context_id' => $match->unit_business_context_id,
                    'product_id' => $match->product_id, 'product_name' => $match->product->rus ?: $match->product->eng,
                    'status' => $match->status->value, 'stale_after' => $match->stale_after?->toISOString(),
                ]),
                'good_matches' => $goodMatches->map(fn (UnitGoodMatch $match) => [
                    'id' => $match->id, 'context_id' => $match->unit_business_context_id,
                    'product_match_id' => $match->unit_product_match_id, 'good_id' => $match->good_id,
                    'good_name' => $match->good->name, 'status' => $match->status->value,
                    'fit_status' => $match->fit_status?->value,
                ]),
                'contacts' => $contacts->map(fn (UnitContactContextLink $contact) => [
                    'id' => $contact->id, 'context_id' => $contact->unit_business_context_id,
                    'email_id' => $contact->email_id,
                    'address' => $contactContextIds->contains($contact->unit_business_context_id) ? $contact->email?->address : null,
                    'display_label' => $contactContextIds->contains($contact->unit_business_context_id)
                        ? $contact->email?->address
                        : 'Email link #'.$contact->id,
                    'communication_state' => $contact->communication_state?->value,
                    'review_required' => $contact->review_required,
                ]),
                'capabilities' => [
                    'can_draft' => $contextIdsFor(OutreachAuthorizationService::DRAFT)->isNotEmpty(),
                    'can_review' => $contextIdsFor(OutreachAuthorizationService::REVIEW)->isNotEmpty(),
                    'can_review_claims' => $contextIdsFor(OutreachAuthorizationService::REVIEW_CLAIMS)->isNotEmpty(),
                    'can_view_permissions' => $permissionContextIds->isNotEmpty(),
                    'can_manage_permissions' => $contextIdsFor(OutreachAuthorizationService::MANAGE_PERMISSIONS)->isNotEmpty(),
                    'can_manage_suppressions' => $contextIdsFor(OutreachAuthorizationService::MANAGE_SUPPRESSIONS)->isNotEmpty(),
                    'can_view_dispatch' => $contextIdsFor(OutreachAuthorizationService::VIEW_DISPATCH)->isNotEmpty(),
                    'can_prepare_dispatch' => $contextIdsFor(OutreachAuthorizationService::PREPARE_DISPATCH)->isNotEmpty(),
                    'can_queue_dispatch' => $contextIdsFor(OutreachAuthorizationService::QUEUE_DISPATCH)->isNotEmpty(),
                    'can_cancel_dispatch' => $contextIdsFor(OutreachAuthorizationService::CANCEL_DISPATCH)->isNotEmpty(),
                    'can_view_events' => $contextIdsFor(OutreachAuthorizationService::VIEW_EVENTS)->isNotEmpty(),
                    'can_view_replies' => $contextIdsFor(OutreachAuthorizationService::VIEW_REPLIES)->isNotEmpty(),
                    'can_review_replies' => $contextIdsFor(OutreachAuthorizationService::REVIEW_REPLIES)->isNotEmpty(),
                    'can_manage_followups' => $contextIdsFor(OutreachAuthorizationService::MANAGE_FOLLOWUPS)->isNotEmpty(),
                ],
                'feature_state' => $features->state(),
            ],
        ]);
    }

    public function storeDraft(
        StoreOutreachDraftRequest $request,
        Unit $unit,
        OutreachDraftService $service,
        OutreachAuthorizationService $authorization,
    ): JsonResponse {
        $context = $this->context($unit, $request->integer('unit_business_context_id'));
        $authorization->authorize($request->user(), OutreachAuthorizationService::DRAFT, $unit, $context);
        $draft = $service->create($request->user(), $unit, $context, $request->validated());

        return response()->json(['data' => ['id' => $draft->id, 'public_id' => $draft->public_id, 'status' => $draft->status->value]], 201);
    }

    public function generate(
        Request $request,
        Unit $unit,
        OutreachDraft $outreachDraft,
        OutreachDraftService $service,
    ): JsonResponse {
        $this->assertDraftUnit($unit, $outreachDraft);
        Gate::authorize('update', $outreachDraft);
        $revision = $service->generate($outreachDraft, $request->user());

        return response()->json(['data' => $this->revisionPayload($revision)], 201);
    }

    public function revise(
        ReviseOutreachDraftRequest $request,
        Unit $unit,
        OutreachDraft $outreachDraft,
        OutreachDraftService $service,
    ): JsonResponse {
        $this->assertDraftUnit($unit, $outreachDraft);
        Gate::authorize('update', $outreachDraft);
        $revision = $service->revise($outreachDraft, $request->user(), $request->validated('structured_content'));

        return response()->json(['data' => $this->revisionPayload($revision)], 201);
    }

    public function review(
        ReviewOutreachDraftRequest $request,
        Unit $unit,
        OutreachDraft $outreachDraft,
        OutreachDraftService $service,
    ): JsonResponse {
        $this->assertDraftUnit($unit, $outreachDraft);
        Gate::authorize('review', $outreachDraft);
        $revision = OutreachDraftRevision::query()->findOrFail($request->integer('revision_id'));
        $review = $service->review(
            $outreachDraft, $revision, $request->user(),
            OutreachReviewType::from($request->validated('review_type')),
            OutreachReviewDecision::from($request->validated('decision')),
            $request->validated('reason_code'), $request->validated('safe_note'),
        );

        return response()->json(['data' => [
            'id' => $review->id, 'review_type' => $review->review_type->value,
            'decision' => $review->decision->value, 'reviewed_at' => $review->reviewed_at->toISOString(),
        ]], 201);
    }

    public function eligibility(
        Request $request,
        Unit $unit,
        OutreachDraft $outreachDraft,
        OutreachDispatchEligibilityService $service,
        OutreachFeatureGuard $features,
    ): JsonResponse {
        $features->drafts();
        $this->assertDraftUnit($unit, $outreachDraft);
        Gate::authorize('view', $outreachDraft);

        return response()->json(['data' => $service->evaluate($outreachDraft, $request->user(), persist: true)->toArray()]);
    }

    public function storePermission(
        StoreCommunicationPermissionRequest $request,
        Unit $unit,
        CommunicationPermissionService $service,
        OutreachAuthorizationService $authorization,
    ): JsonResponse {
        $context = $this->context($unit, $request->integer('unit_business_context_id'));
        $contact = UnitContactContextLink::query()->findOrFail($request->integer('unit_contact_context_link_id'));
        $authorization->authorize($request->user(), OutreachAuthorizationService::MANAGE_PERMISSIONS, $unit, $context);
        $permission = $service->create($request->user(), $unit, $context, $contact, $request->validated());

        return response()->json(['data' => $this->permissionPayload($permission)], 201);
    }

    public function reviewPermission(
        ReviewCommunicationPermissionRequest $request,
        Unit $unit,
        CommunicationPermission $communicationPermission,
        CommunicationPermissionService $service,
    ): JsonResponse {
        abort_unless((int) $communicationPermission->unit_id === (int) $unit->id, 404);
        Gate::authorize('update', $communicationPermission);
        $permission = $service->review(
            $communicationPermission, $request->user(),
            CommunicationPermissionStatus::from($request->validated('decision')),
            $request->validated('reason_code'), $request->validated('safe_note'),
        );

        return response()->json(['data' => $this->permissionPayload($permission)]);
    }

    public function revokePermission(
        RevokeCommunicationPermissionRequest $request,
        Unit $unit,
        CommunicationPermission $communicationPermission,
        CommunicationPermissionService $service,
    ): JsonResponse {
        abort_unless((int) $communicationPermission->unit_id === (int) $unit->id, 404);
        Gate::authorize('update', $communicationPermission);
        $permission = $service->revoke(
            $communicationPermission, $request->user(), $request->validated('reason_code'), $request->validated('safe_note'),
        );

        return response()->json(['data' => $this->permissionPayload($permission)]);
    }

    private function context(Unit $unit, int $id): UnitBusinessContext
    {
        return UnitBusinessContext::query()->where('unit_id', $unit->id)->findOrFail($id);
    }

    private function assertDraftUnit(Unit $unit, OutreachDraft $draft): void
    {
        abort_unless((int) $draft->unit_id === (int) $unit->id, 404);
    }

    private function draftPayload(
        OutreachDraft $draft,
        OutreachDispatchEligibilityService $eligibility,
        bool $mayViewContact,
    ): array {
        $revision = $draft->currentRevision();

        return [
            'id' => $draft->id, 'public_id' => $draft->public_id, 'context_id' => $draft->unit_business_context_id,
            'purpose' => $draft->purpose->value, 'status' => $draft->status->value,
            'generation_origin' => $draft->generation_origin->value,
            'product' => ['id' => $draft->productMatch->product_id, 'name' => $draft->productMatch->product->rus ?: $draft->productMatch->product->eng],
            'good' => $draft->goodMatch ? ['id' => $draft->goodMatch->good_id, 'name' => $draft->goodMatch->good->name] : null,
            'recipient' => $draft->contactLink?->email ? [
                'contact_link_id' => $draft->contactLink->id,
                'email_id' => $draft->email_id,
                'address' => $mayViewContact ? $draft->contactLink->email->address : null,
            ] : null,
            'current_revision' => $revision ? $this->revisionPayload($revision) : null,
            'eligibility' => $eligibility->evaluate($draft)->toArray(),
            'expires_at' => $draft->expires_at?->toISOString(), 'created_at' => $draft->created_at?->toISOString(),
        ];
    }

    private function revisionPayload(OutreachDraftRevision $revision): array
    {
        return [
            'id' => $revision->id, 'public_id' => $revision->public_id, 'revision_number' => $revision->revision_number,
            'origin' => $revision->origin->value, 'structured_content' => $revision->structured_content,
            'subject' => $revision->subject, 'plaintext' => $revision->plaintext, 'html' => $revision->html,
            'dlp_status' => $revision->dlp_status->value, 'dlp_findings' => $revision->dlp_findings,
            'renderer_version' => $revision->renderer_version, 'created_at' => $revision->created_at?->toISOString(),
        ];
    }

    private function permissionPayload(CommunicationPermission $permission): array
    {
        return [
            'id' => $permission->id, 'public_id' => $permission->public_id,
            'context_id' => $permission->unit_business_context_id, 'contact_link_id' => $permission->unit_contact_context_link_id,
            'email_id' => $permission->email_id, 'purpose' => $permission->purpose->value,
            'product_id' => $permission->product_id, 'sender_scope' => $permission->sender_scope,
            'status' => $permission->status->value, 'valid_from' => $permission->valid_from?->toISOString(),
            'valid_until' => $permission->valid_until?->toISOString(), 'evidence_count' => $permission->evidence()->count(),
            'reviewed_at' => $permission->reviewed_at?->toISOString(),
        ];
    }

    private function suppressionPayload(CommunicationSuppression $suppression): array
    {
        return [
            'id' => $suppression->id, 'public_id' => $suppression->public_id, 'scope' => $suppression->scope->value,
            'context_id' => $suppression->unit_business_context_id, 'reason' => $suppression->reason->value,
            'source' => $suppression->source, 'active_from' => $suppression->active_from?->toISOString(),
            'active_until' => $suppression->active_until?->toISOString(), 'cleared_at' => $suppression->cleared_at?->toISOString(),
        ];
    }
}
