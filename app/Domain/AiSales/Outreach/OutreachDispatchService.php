<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachRevalidationCheckpoint;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Jobs\AiSales\SendOutreachDispatchJob;
use App\Models\CommunicationPermission;
use App\Models\CommunicationSuppression;
use App\Models\MailMessage;
use App\Models\OutreachDispatch;
use App\Models\OutreachDispatchDecision;
use App\Models\OutreachDraft;
use App\Models\Sending;
use App\Models\UnitContactContextLink;
use App\Models\User;
use App\Services\CommercialOffers\MailProviderException;
use App\Services\CommercialOffers\MailProviderSafeErrorCode;
use App\Services\CommercialOffers\UnisenderGoClient;
use App\Services\CommercialOffers\UnisenderRequestProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class OutreachDispatchService
{
    public function __construct(
        private readonly OutreachFeatureGuard $features,
        private readonly OutreachAuthorizationService $authorization,
        private readonly OutreachFinalRevalidationService $revalidation,
        private readonly OutreachDispatchMessageMapper $messages,
        private readonly OutreachDispatchStateMachine $states,
        private readonly UnisenderGoClient $provider,
    ) {}

    public function prepare(OutreachDraft $draft, User $actor, string $idempotencyKey): OutreachDispatchActionResult
    {
        $this->features->dispatchPipeline();
        $idempotencyHash = hash('sha256', Str::lower(trim($idempotencyKey)));

        return DB::transaction(function () use ($draft, $actor, $idempotencyHash): OutreachDispatchActionResult {
            $draft = OutreachDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $this->lockRevalidationScope($draft);
            $draft->unsetRelations();
            $draft->load([
                'unit', 'businessContext', 'contactLink.email', 'productMatch', 'goodMatch',
                'revisions.claims', 'reviews', 'productRelevanceSnapshot', 'goodFitSnapshot',
                'prospectPrioritySnapshot',
            ]);
            $this->authorization->authorize($actor, OutreachAuthorizationService::PREPARE_DISPATCH, $draft->unit, $draft->businessContext);

            $existing = OutreachDispatch::query()->where('idempotency_hash', $idempotencyHash)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->outreach_draft_id !== (int) $draft->id) {
                    throw new PolicyViolation('outreach_idempotency_scope_mismatch', 'Idempotency key belongs to another outreach draft.');
                }

                $result = $this->revalidation->evaluate($draft, $actor, OutreachRevalidationCheckpoint::Prepare, $existing);
                $this->applyPrepareReplayDecision($draft, $existing, $result, $actor);

                return new OutreachDispatchActionResult($existing->fresh(), $result, $result->eligible);
            }

            $revision = $draft->currentRevision();
            $logical = $revision ? OutreachDispatch::query()
                ->where('outreach_draft_revision_id', $revision->id)
                ->where('unit_contact_context_link_id', $draft->unit_contact_context_link_id)
                ->where('purpose', $draft->purpose->value)
                ->lockForUpdate()
                ->first() : null;
            if ($logical) {
                $result = $this->revalidation->evaluate($draft, $actor, OutreachRevalidationCheckpoint::Prepare, $logical);
                $this->applyPrepareReplayDecision($draft, $logical, $result, $actor);

                return new OutreachDispatchActionResult($logical->fresh(), $result, $result->eligible);
            }

            $result = $this->revalidation->evaluate($draft, $actor, OutreachRevalidationCheckpoint::Prepare);
            if (! $result->eligible || ! $result->permissionId) {
                throw new PolicyViolation('outreach_prepare_blocked', implode(',', $result->blockReasons));
            }

            $permission = CommunicationPermission::query()->lockForUpdate()->findOrFail($result->permissionId);
            UnitContactContextLink::query()->whereKey($draft->unit_contact_context_link_id)->lockForUpdate()->firstOrFail();
            $unsubscribeToken = Str::random(64);
            $dispatch = OutreachDispatch::query()->create([
                'public_id' => (string) Str::uuid(),
                'unit_id' => $draft->unit_id,
                'unit_business_context_id' => $draft->unit_business_context_id,
                'outreach_draft_id' => $draft->id,
                'outreach_draft_revision_id' => $revision->id,
                'communication_permission_id' => $permission->id,
                'unit_contact_context_link_id' => $draft->unit_contact_context_link_id,
                'unit_product_match_id' => $draft->unit_product_match_id,
                'unit_good_match_id' => $draft->unit_good_match_id,
                'purpose' => $draft->purpose,
                'state' => OutreachDispatchState::Ready,
                'request_profile' => UnisenderRequestProfile::OutreachZeroRetry->value,
                'idempotency_hash' => $idempotencyHash,
                'revision_hash' => $result->revisionHash,
                'renderer_hash' => $revision->renderer_hash,
                'dlp_hash' => $revision->dlp_hash,
                'evidence_hash' => $draft->evidence_hash,
                'permission_scope_hash' => $result->permissionScopeHash,
                'sender_config_hash' => $result->senderConfigHash,
                'unsubscribe_token_hash' => hash('sha256', $unsubscribeToken),
                'last_revalidation_hash' => $result->decisionHash,
                'safe_summary' => 'prepared_after_final_review',
                'prepared_by' => $actor->id,
                'prepared_at' => now(),
                'last_revalidated_at' => now(),
            ]);

            $mapped = $this->messages->map($revision, route('mailings.unsubscribe.show', $unsubscribeToken));
            $mailMessage = MailMessage::query()->create([
                'mailbox' => Str::lower((string) config('services.unisender_go.from_email')),
                'folder' => 'OutreachOutbox',
                'direction' => 'outgoing',
                'message_id' => '<'.Str::uuid().'@outreach.pischeprom>',
                'subject' => $mapped['subject'],
                'message_date' => now(),
                'from_address' => Str::lower((string) config('services.unisender_go.from_email')),
                'from_name' => mb_substr((string) config('services.unisender_go.from_name'), 0, 255),
                'to' => [],
                'cc' => [],
                'preview' => Str::limit(trim(strip_tags($mapped['plaintext'])), 250),
                'html' => $mapped['html'],
                'text' => $mapped['plaintext'],
                'body_loaded_at' => now(),
                'has_attachments' => false,
            ]);
            $mailMessage->emails()->syncWithoutDetaching([$draft->email_id => ['role' => 'to']]);

            $sending = Sending::query()->create([
                'email_id' => $draft->email_id,
                'mail_message_id' => $mailMessage->id,
                'subject' => $mapped['subject'],
                'provider' => 'unisender_go',
                'status' => 'prepared',
                'request_profile' => UnisenderRequestProfile::OutreachZeroRetry->value,
                'safe_summary' => 'outreach_outbox_prepared',
            ]);
            $dispatch->forceFill(['mail_message_id' => $mailMessage->id, 'sending_id' => $sending->id])->save();
            $this->recordDecision($draft, $dispatch, $result, OutreachRevalidationCheckpoint::Prepare, $actor);

            return new OutreachDispatchActionResult($dispatch->fresh(), $result, true);
        });
    }

    public function queue(OutreachDispatch $dispatch, User $actor): OutreachDispatchActionResult
    {
        $this->features->queue();

        $result = DB::transaction(function () use ($dispatch, $actor): OutreachDispatchActionResult {
            $dispatch = OutreachDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $draft = OutreachDraft::query()->lockForUpdate()->findOrFail($dispatch->outreach_draft_id);
            $this->lockRevalidationScope($draft);
            $draft->unsetRelations();
            $draft->load([
                'unit', 'businessContext', 'contactLink.email', 'productMatch', 'goodMatch',
                'revisions.claims', 'reviews', 'productRelevanceSnapshot', 'goodFitSnapshot',
                'prospectPrioritySnapshot',
            ]);
            $this->authorization->authorize($actor, OutreachAuthorizationService::QUEUE_DISPATCH, $draft->unit, $draft->businessContext);

            if (in_array($dispatch->state, [OutreachDispatchState::QueuePending, OutreachDispatchState::Queued], true)) {
                $revalidation = $this->revalidation->evaluate($draft, $actor, OutreachRevalidationCheckpoint::Queue, $dispatch);
                $this->recordDecision($draft, $dispatch, $revalidation, OutreachRevalidationCheckpoint::Queue, $actor);
                $dispatch->forceFill([
                    'last_revalidation_hash' => $revalidation->decisionHash,
                    'last_revalidated_at' => now(),
                    'last_block_reason' => $revalidation->blockReasons[0] ?? null,
                    'lock_version' => $dispatch->lock_version + 1,
                ])->save();
                if (! $revalidation->eligible) {
                    $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'queue_replay_revalidation_blocked');

                    return new OutreachDispatchActionResult($dispatch->fresh(), $revalidation, false);
                }

                return new OutreachDispatchActionResult($dispatch->fresh(), $revalidation, true);
            }
            if ($dispatch->state !== OutreachDispatchState::Ready) {
                throw new PolicyViolation('outreach_dispatch_not_queueable', 'Outreach dispatch is not queueable in its current state.');
            }

            $revalidation = $this->revalidation->evaluate($draft, $actor, OutreachRevalidationCheckpoint::Queue, $dispatch);
            $this->recordDecision($draft, $dispatch, $revalidation, OutreachRevalidationCheckpoint::Queue, $actor);
            $dispatch->forceFill([
                'last_revalidation_hash' => $revalidation->decisionHash,
                'last_revalidated_at' => now(),
                'last_block_reason' => $revalidation->blockReasons[0] ?? null,
                'queued_by' => $actor->id,
                'lock_version' => $dispatch->lock_version + 1,
            ])->save();
            if (! $revalidation->eligible) {
                $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'queue_revalidation_blocked');

                return new OutreachDispatchActionResult($dispatch->fresh(), $revalidation, false);
            }

            $dispatch->forceFill([
                'state' => OutreachDispatchState::QueuePending,
                'queue_requested_at' => now(),
                'safe_summary' => 'durable_queue_intent_created',
            ])->save();
            $dispatch->sending?->forceFill(['status' => 'queued', 'safe_summary' => 'outreach_queue_pending'])->save();

            SendOutreachDispatchJob::dispatch($dispatch->id)->afterCommit();

            return new OutreachDispatchActionResult($dispatch->fresh(), $revalidation, true);
        });

        return $result;
    }

    public function cancel(OutreachDispatch $dispatch, User $actor, string $reasonCode): OutreachDispatch
    {
        return DB::transaction(function () use ($dispatch, $actor, $reasonCode): OutreachDispatch {
            $dispatch = OutreachDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $dispatch->loadMissing('draft.unit', 'draft.businessContext');
            $this->authorization->authorize($actor, OutreachAuthorizationService::CANCEL_DISPATCH, $dispatch->draft->unit, $dispatch->draft->businessContext);
            if (in_array($dispatch->state, [
                OutreachDispatchState::ProviderAccepted, OutreachDispatchState::Sent, OutreachDispatchState::Delivered,
                OutreachDispatchState::HardBounced, OutreachDispatchState::Complained,
                OutreachDispatchState::Unsubscribed, OutreachDispatchState::Replied,
                OutreachDispatchState::AmbiguousAcceptance,
            ], true)) {
                throw new PolicyViolation('outreach_dispatch_already_provider_bound', 'Provider-bound outreach cannot be marked unsent.');
            }
            $dispatch->forceFill(['last_block_reason' => mb_substr($reasonCode, 0, 64)])->save();
            $this->states->transition($dispatch, OutreachDispatchState::Cancelled, 'cancelled_by_authorized_operator');
            $dispatch->followUpPlan?->forceFill([
                'status' => OutreachFollowUpStatus::Expired, 'cancellation_reason' => mb_substr($reasonCode, 0, 64),
            ])->save();

            return $dispatch->fresh();
        });
    }

    public function deliver(int $dispatchId): void
    {
        DB::transaction(function () use ($dispatchId): void {
            $dispatch = OutreachDispatch::query()->lockForUpdate()->findOrFail($dispatchId);
            if (! in_array($dispatch->state, [OutreachDispatchState::QueuePending, OutreachDispatchState::Queued], true)) {
                return;
            }
            $dispatch->loadMissing(['mailMessage', 'sending', 'contactLink.email', 'queuer']);
            if (! $dispatch->queuer) {
                $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'queue_actor_missing');

                return;
            }

            try {
                $this->features->providerSend();
            } catch (PolicyViolation) {
                $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'provider_feature_guard_blocked');

                return;
            }

            $draft = OutreachDraft::query()->lockForUpdate()->findOrFail($dispatch->outreach_draft_id);
            $this->lockRevalidationScope($draft);
            $draft->unsetRelations();
            $draft->load([
                'unit', 'businessContext', 'contactLink.email', 'productMatch', 'goodMatch',
                'revisions.claims', 'reviews', 'productRelevanceSnapshot', 'goodFitSnapshot',
                'prospectPrioritySnapshot',
            ]);
            $dispatch->setRelation('draft', $draft);
            $revalidation = $this->revalidation->evaluate($draft, $dispatch->queuer, OutreachRevalidationCheckpoint::Worker, $dispatch);
            $this->recordDecision($draft, $dispatch, $revalidation, OutreachRevalidationCheckpoint::Worker, $dispatch->queuer);
            $dispatch->forceFill([
                'last_revalidation_hash' => $revalidation->decisionHash,
                'last_revalidated_at' => now(),
                'last_block_reason' => $revalidation->blockReasons[0] ?? null,
            ])->save();
            if (! $revalidation->eligible) {
                $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'worker_revalidation_blocked');

                return;
            }

            $this->states->transition($dispatch, OutreachDispatchState::Queued, 'worker_final_guard_passed');
            $message = $this->providerMessage($dispatch);
            $requestHash = AiCanonicalJson::hash([
                'dispatch_public_id' => $dispatch->public_id,
                'recipient_hash' => hash('sha256', $dispatch->contactLink->email->address),
                'subject_hash' => hash('sha256', $dispatch->mailMessage->subject),
                'plaintext_hash' => hash('sha256', $dispatch->mailMessage->text),
                'html_hash' => hash('sha256', $dispatch->mailMessage->html),
                'profile' => UnisenderRequestProfile::OutreachZeroRetry->value,
            ]);
            $dispatch->sending->forceFill(['request_hash' => $requestHash])->save();

            try {
                $result = $this->provider->sendEmail($message, UnisenderRequestProfile::OutreachZeroRetry);
                if ($result->failedEmails !== []) {
                    $dispatch->sending->forceFill([
                        'status' => 'failed', 'provider_message_id' => $result->jobId,
                        'response_hash' => $result->responseHash, 'http_status_category' => $result->httpStatusCategory,
                        'safe_request_id' => $result->safeRequestId,
                        'safe_error_code' => MailProviderSafeErrorCode::PermissionDenied->value,
                        'safe_summary' => 'provider_recipient_rejected_safe',
                    ])->save();
                    $dispatch->forceFill(['provider_job_id' => $result->jobId])->save();
                    $this->states->transition($dispatch, OutreachDispatchState::Failed, 'provider_recipient_rejected_safe');

                    return;
                }
                $dispatch->sending->forceFill([
                    'status' => 'accepted', 'provider_message_id' => $result->jobId,
                    'response_hash' => $result->responseHash, 'http_status_category' => $result->httpStatusCategory,
                    'safe_request_id' => $result->safeRequestId, 'safe_error_code' => null,
                    'safe_summary' => 'provider_accepted', 'sent_at' => now(),
                ])->save();
                $dispatch->forceFill(['provider_job_id' => $result->jobId])->save();
                $this->states->transition($dispatch, OutreachDispatchState::ProviderAccepted, 'provider_accepted_zero_retry');
            } catch (MailProviderException $exception) {
                $ambiguous = $exception->ambiguousAcceptance;
                $dispatch->sending->forceFill([
                    'status' => $ambiguous ? 'ambiguous_acceptance' : 'failed',
                    'response_hash' => $exception->responseHash,
                    'http_status_category' => $exception->httpStatusCategory,
                    'safe_request_id' => $exception->safeRequestId,
                    'safe_error_code' => $exception->safeCode->value,
                    'safe_summary' => $ambiguous ? 'operator_review_required_no_resend' : 'provider_rejected_safe',
                    'ambiguous_acceptance_at' => $ambiguous ? now() : null,
                ])->save();
                $this->states->transition(
                    $dispatch,
                    $ambiguous ? OutreachDispatchState::AmbiguousAcceptance : OutreachDispatchState::Failed,
                    $ambiguous ? 'ambiguous_acceptance_no_resend' : 'provider_failed_safe',
                );
            } catch (Throwable) {
                $dispatch->sending->forceFill([
                    'status' => 'failed', 'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
                    'safe_summary' => 'provider_processing_failed_safe',
                ])->save();
                $this->states->transition($dispatch, OutreachDispatchState::Failed, 'provider_processing_failed_safe');
            }
        });
    }

    private function applyPrepareReplayDecision(
        OutreachDraft $draft,
        OutreachDispatch $dispatch,
        OutreachFinalRevalidationResult $result,
        User $actor,
    ): void {
        $this->recordDecision($draft, $dispatch, $result, OutreachRevalidationCheckpoint::Prepare, $actor);
        $dispatch->forceFill([
            'last_revalidation_hash' => $result->decisionHash,
            'last_revalidated_at' => now(),
            'last_block_reason' => $result->blockReasons[0] ?? null,
            'lock_version' => $dispatch->lock_version + 1,
        ])->save();
        if (! $result->eligible) {
            $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'prepare_replay_revalidation_blocked');
        }
    }

    private function lockRevalidationScope(OutreachDraft $draft): void
    {
        $draft->unit()->lockForUpdate()->firstOrFail();
        $draft->businessContext()->lockForUpdate()->firstOrFail();
        $contact = $draft->contactLink()->lockForUpdate()->first();
        $email = $contact?->email()->lockForUpdate()->first();
        $draft->productMatch()->lockForUpdate()->firstOrFail();
        $draft->goodMatch()->lockForUpdate()->first();
        $draft->productRelevanceSnapshot()->lockForUpdate()->first();
        $draft->goodFitSnapshot()->lockForUpdate()->first();
        $draft->prospectPrioritySnapshot()->lockForUpdate()->first();

        $revision = $draft->revisions()
            ->where('revision_number', $draft->current_revision_number)
            ->lockForUpdate()
            ->first();
        if ($revision) {
            $revision->claims()->lockForUpdate()->get();
            $draft->reviews()->where('outreach_draft_revision_id', $revision->id)->lockForUpdate()->get();
        }

        CommunicationPermission::query()
            ->where('unit_business_context_id', $draft->unit_business_context_id)
            ->where('unit_contact_context_link_id', $draft->unit_contact_context_link_id)
            ->where('product_id', $draft->productMatch()->value('product_id'))
            ->where('purpose', $draft->purpose->value)
            ->where('sender_scope', (string) config('ai-sales.outreach.sender_scope'))
            ->lockForUpdate()
            ->get();

        if (! $contact || ! $email) {
            return;
        }
        $endpointHash = hash('sha256', Str::lower(trim($email->address)));
        $domain = Str::after(Str::lower(trim($email->address)), '@');
        $domainHash = $domain !== '' ? hash('sha256', $domain) : null;
        CommunicationSuppression::query()
            ->where('channel', 'email')
            ->whereNull('cleared_at')
            ->where(function ($query) use ($draft, $endpointHash, $domainHash): void {
                $query->where('scope', 'global')
                    ->orWhere(fn ($nested) => $nested->where('scope', 'unit')->where('unit_id', $draft->unit_id))
                    ->orWhere(fn ($nested) => $nested->where('scope', 'context')->where('unit_business_context_id', $draft->unit_business_context_id))
                    ->orWhere(fn ($nested) => $nested->where('scope', 'endpoint')->where('endpoint_hash', $endpointHash));
                if ($domainHash) {
                    $query->orWhere(fn ($nested) => $nested->where('scope', 'domain')->where('domain_hash', $domainHash));
                }
            })
            ->lockForUpdate()
            ->get();
    }

    private function providerMessage(OutreachDispatch $dispatch): array
    {
        $mail = $dispatch->mailMessage;
        $email = $dispatch->contactLink->email;

        return [
            'recipients' => [[
                'email' => $email->address,
                'metadata' => [
                    'sending_id' => (string) $dispatch->sending_id,
                    'mail_message_id' => (string) $dispatch->mail_message_id,
                ],
            ]],
            'body' => ['html' => $mail->html, 'plaintext' => $mail->text],
            'subject' => $mail->subject,
            'from_email' => (string) config('services.unisender_go.from_email'),
            'from_name' => (string) config('services.unisender_go.from_name'),
            'reply_to' => (string) config('services.unisender_go.reply_to'),
            'track_links' => (bool) config('services.unisender_go.track_links', true) ? 1 : 0,
            'track_read' => (bool) config('services.unisender_go.track_read', true) ? 1 : 0,
            'global_metadata' => ['workflow' => 'reviewed_outreach'],
            'tags' => ['reviewed_outreach'],
            'idempotence_key' => 'outreach-'.$dispatch->public_id,
        ];
    }

    private function recordDecision(
        OutreachDraft $draft,
        OutreachDispatch $dispatch,
        OutreachFinalRevalidationResult $result,
        OutreachRevalidationCheckpoint $checkpoint,
        User $actor,
    ): void {
        OutreachDispatchDecision::query()->create([
            'public_id' => (string) Str::uuid(),
            'outreach_draft_id' => $draft->id,
            'outreach_draft_revision_id' => $draft->currentRevision()?->id,
            'outreach_dispatch_id' => $dispatch->id,
            'checkpoint' => $checkpoint->value,
            'communication_permission_id' => $result->permissionId,
            'eligible' => $result->eligible,
            'block_reasons' => $result->blockReasons,
            'decision_hash' => AiCanonicalJson::hash([
                'revalidation_hash' => $result->decisionHash,
                'dispatch_public_id' => $dispatch->public_id,
                'checkpoint' => $checkpoint->value,
                'sequence' => $dispatch->decisions()->count() + 1,
            ]),
            'policy_version' => (string) config('ai-sales.outreach.dispatch_policy_version', 'stage13-v1'),
            'evaluated_by' => $actor->id,
            'evaluated_at' => now(),
        ]);
    }
}
