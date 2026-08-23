<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingMessage;
use App\Models\MailingSuppression;
use App\Models\MailingTemplate;
use App\Models\MailingTemplateVersion;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MailingCampaignService
{
    public function __construct(
        private readonly UnisenderGoClient $client,
        private readonly MailingRenderer $renderer,
        private readonly RecipientSetService $recipientSets,
        private readonly MailingAuditLogger $audit,
    ) {}

    public function createDraft(array $data, ?int $userId = null): MailingCampaign
    {
        $template = isset($data['template_id']) ? MailingTemplate::query()->find($data['template_id']) : null;

        $campaign = MailingCampaign::query()->create([
            'name' => $data['name'] ?? 'Новое КП '.now()->format('d.m.Y H:i'),
            'type' => $data['type'] ?? 'mass_offer',
            'status' => 'draft',
            'subject' => $data['subject'] ?? $template?->subject ?? 'Коммерческое предложение',
            'preheader' => $data['preheader'] ?? $template?->preheader,
            'template_id' => $template?->id ?? ($data['template_id'] ?? null),
            'contact_set_id' => $data['contact_set_id'] ?? null,
            'html_markup' => $data['html_markup'] ?? $template?->html_markup,
            'plaintext' => $data['plaintext'] ?? $template?->plaintext,
            'from_email' => $this->providerFromEmail(),
            'from_name' => $this->providerFromName(),
            'reply_to' => $this->providerReplyTo(),
            'created_by' => $userId,
            'updated_by' => $userId,
            'compliance_status' => $data['compliance_status'] ?? 'draft',
            'compliance_note' => $data['compliance_note'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'tags' => $data['tags'] ?? null,
        ]);

        $this->audit->log('campaign.created', MailingCampaign::class, $campaign->id, null, $campaign->toArray(), userId: $userId);

        return $campaign;
    }

    public function updateDraft(MailingCampaign|int $campaign, array $data, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        if (in_array($campaign->status, ['sending', 'completed', 'cancelled'], true)) {
            throw new RuntimeException('Campaign cannot be edited in status '.$campaign->status.'.');
        }

        $before = $campaign->toArray();
        $campaign->fill(Arr::only($data, [
            'name', 'type', 'subject', 'preheader', 'template_id', 'contact_set_id', 'html_markup', 'plaintext',
            'from_email', 'from_name', 'reply_to', 'scheduled_at', 'compliance_status', 'compliance_note', 'metadata', 'tags',
        ]));
        $campaign->forceFill($this->providerSenderPayload());
        $campaign->updated_by = $userId;
        $campaign->save();

        $this->audit->log('campaign.updated', MailingCampaign::class, $campaign->id, $before, $campaign->fresh()->toArray(), userId: $userId);

        return $campaign->fresh();
    }

    public function preview(MailingCampaign|int $campaign, MailingContact|int|null $contact = null): array
    {
        $campaign = $this->campaign($campaign);
        $contact = $contact instanceof MailingContact ? $contact : ($contact ? MailingContact::query()->find($contact) : MailingContact::query()->first());
        $contact ??= new MailingContact(['email' => 'preview@example.ru', 'first_name' => 'Получатель', 'company_name' => 'Компания']);
        $products = $campaign->offerItems()->get()->all();
        $html = $this->renderer->renderCampaignHtml($campaign, $contact, $products);

        return [
            'html' => $html,
            'plaintext' => $this->renderer->renderPlaintext($campaign, $contact, $products),
            'preheader' => $this->renderer->generatePreheader($html, $campaign->preheader),
            'errors' => $this->renderer->validateEmailHtml($html),
        ];
    }

    public function sendTest(MailingCampaign|int $campaign, string $email, ?int $userId = null): UnisenderSendResult
    {
        $campaign = $this->campaign($campaign);
        $campaign = $this->syncCampaignProviderSender($campaign);
        $this->validateBeforeSend($campaign, requireRecipients: false);

        $email = MailingContact::normalizeEmail($email ?: (string) config('services.mailings.test_recipient'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Valid test recipient email is required.');
        }
        $this->assertRecipientCanReceiveTest($email);

        $recipient = MailingCampaignRecipient::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'normalized_email' => $email],
            ['email' => $email, 'status' => 'queued', 'metadata' => ['test' => true]]
        );
        $contact = MailingContact::query()->firstOrCreate(
            ['normalized_email' => $email],
            ['email' => $email, 'consent_status' => 'not_required_internal', 'contact_source' => 'test_send']
        );
        $recipient->update(['contact_id' => $contact->id]);

        $message = $this->buildMessage($campaign, collect([$recipient]), true);
        $mailingMessage = MailingMessage::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'contact_id' => $contact->id,
            'email' => $email,
            'subject' => '[TEST] '.$campaign->subject,
            'status' => 'queued',
            'request_hash' => $this->requestHash($message),
            'request_profile' => UnisenderRequestProfile::LegacyManual->value,
            'safe_summary' => 'queued_for_provider',
        ]);

        try {
            $result = $this->client->sendEmail($message);
        } catch (RuntimeException $exception) {
            if (! $this->isTrackingConfigurationRejection($exception)) {
                $this->markRecipientSendFailed($recipient, $exception);
                $this->markMailingMessageFailed($mailingMessage, $exception);

                throw $exception;
            }

            Log::warning('Retrying Unisender test send with tracking disabled', [
                'provider' => 'unisender_go',
                'campaign_id' => $campaign->id,
                'safe_error_code' => MailProviderSafeErrorCode::PermissionDenied->value,
                'safe_detail_code' => 'tracking_configuration_required',
            ]);

            $message['track_read'] = 0;
            $message['track_links'] = 0;
            $message['global_metadata']['tracking_disabled_for_test'] = true;

            try {
                $retryResult = $this->client->sendEmail($message);
            } catch (RuntimeException $retryException) {
                $this->markRecipientSendFailed($recipient, $retryException);
                $this->markMailingMessageFailed($mailingMessage, $retryException);

                throw $retryException;
            }

            $result = new UnisenderSendResult(
                successful: $retryResult->successful,
                jobId: $retryResult->jobId,
                failedEmails: $retryResult->failedEmails,
                httpStatusCategory: $retryResult->httpStatusCategory,
                safeRequestId: $retryResult->safeRequestId,
                responseHash: $retryResult->responseHash,
                requestProfile: $retryResult->requestProfile,
                safeResponseMetadata: ['tracking_disabled_for_test' => true],
            );
        }

        $recipient->update([
            'status' => 'accepted',
            'sent_at' => now(),
            'unisender_job_id' => $result->jobId,
            'idempotence_key' => $message['idempotence_key'],
            'safe_error_code' => null,
            'safe_summary' => 'provider_accepted',
        ]);

        $mailingMessage->update([
            'status' => 'accepted',
            'unisender_job_id' => $result->jobId,
            'request_hash' => $this->requestHash($message),
            'response_hash' => $result->responseHash,
            'request_profile' => $result->requestProfile->value,
            'http_status_category' => $result->httpStatusCategory,
            'safe_request_id' => $result->safeRequestId,
            'safe_summary' => 'provider_accepted',
        ]);

        $this->audit->log('campaign.test_sent', MailingCampaign::class, $campaign->id, null, [
            'recipient_count' => 1,
            'job_id' => $result->jobId,
        ], userId: $userId);

        return $result;
    }

    public function approve(MailingCampaign|int $campaign, ?int $userId = null, ?string $note = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $campaign = $this->syncCampaignProviderSender($campaign);
        $this->validateBeforeSend($campaign);
        $campaign->update([
            'status' => 'ready',
            'compliance_status' => 'approved',
            'compliance_note' => $note ?: $campaign->compliance_note,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->audit->log('campaign.approved', MailingCampaign::class, $campaign->id, null, ['note' => $note], userId: $userId);

        return $campaign->fresh();
    }

    public function schedule(MailingCampaign|int $campaign, DateTimeInterface|string $sendAt, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $campaign = $this->syncCampaignProviderSender($campaign);
        $this->validateBeforeSend($campaign);
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $sendAt]);
        $this->audit->log('campaign.scheduled', MailingCampaign::class, $campaign->id, null, ['scheduled_at' => (string) $campaign->scheduled_at], userId: $userId);

        return $campaign->fresh();
    }

    public function startSending(MailingCampaign|int $campaign, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $campaign = $this->syncCampaignProviderSender($campaign);
        $this->validateBeforeSend($campaign);

        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            throw new RuntimeException('Campaign is already '.$campaign->status.'.');
        }

        $this->ensureCampaignRecipients($campaign);
        $pending = $campaign->recipients()
            ->whereIn('status', ['pending', 'queued', 'failed'])
            ->whereNull('sent_at')
            ->whereNotIn('normalized_email', MailingSuppression::query()->select('normalized_email'))
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
            $this->recalculateStats($campaign);

            return $campaign->fresh();
        }

        $campaign->update(['status' => 'sending', 'started_at' => $campaign->started_at ?: now()]);
        $batchSize = min(500, max(1, (int) config('services.mailings.batch_size', 500)));

        foreach ($pending->chunk($batchSize) as $chunk) {
            if ((bool) config('services.mailings.dry_run', false)) {
                $chunk->each->update(['status' => 'accepted', 'sent_at' => now()]);

                continue;
            }

            $message = $this->buildMessage($campaign, $chunk);
            $mailingMessages = $this->createMailingMessages($campaign, $chunk);
            $message['recipients'] = $this->attachMessageIdsToRecipientMetadata($message['recipients'], $mailingMessages);
            $requestHash = $this->requestHash($message);
            $mailingMessages->each->update(['request_hash' => $requestHash]);

            try {
                $result = $this->client->sendEmail($message);
            } catch (RuntimeException $exception) {
                $safeCode = $this->safeErrorCode($exception);
                $ambiguous = $exception instanceof MailProviderException && $exception->ambiguousAcceptance;
                $providerException = $exception instanceof MailProviderException ? $exception : null;
                $campaign->update(['status' => $ambiguous ? 'paused_by_system' : 'failed']);
                $chunk->each(fn (MailingCampaignRecipient $recipient) => $recipient->update([
                    'status' => $ambiguous ? 'operator_review' : 'failed',
                    'safe_error_code' => $safeCode,
                    'safe_summary' => $ambiguous ? 'operator_review_no_resend' : 'provider_send_failed_safe',
                ]));
                $mailingMessages->each->update([
                    'status' => $ambiguous ? 'operator_review' : 'failed',
                    'safe_error_code' => $safeCode,
                    'safe_summary' => $ambiguous ? 'operator_review_no_resend' : 'provider_send_failed_safe',
                    'ambiguous_acceptance_at' => $ambiguous ? now() : null,
                    'response_hash' => $providerException?->responseHash,
                    'http_status_category' => $providerException?->httpStatusCategory,
                    'safe_request_id' => $providerException?->safeRequestId,
                ]);
                Log::error('Unisender campaign send failed', [
                    'campaign_id' => $campaign->id,
                    'safe_error_code' => $safeCode,
                    'exception_type' => $exception::class,
                ]);
                throw $exception;
            }

            $failedEmails = $this->failedEmailMap($result->failedEmails);
            foreach ($chunk as $recipient) {
                $failedReason = $failedEmails[$recipient->normalized_email] ?? null;
                $recipient->update([
                    'status' => $failedReason ? 'failed' : 'accepted',
                    'safe_error_code' => $failedReason,
                    'safe_summary' => $failedReason ? 'recipient_rejected_safe' : 'provider_accepted',
                    'sent_at' => $failedReason ? null : now(),
                    'unisender_job_id' => $result->jobId,
                    'idempotence_key' => $message['idempotence_key'].'-'.$recipient->id,
                    'metadata' => array_merge($recipient->metadata ?: [], ['batch_idempotence_key' => $message['idempotence_key']]),
                ]);

                if (! $failedReason && $recipient->contact) {
                    $recipient->contact->update(['last_contacted_at' => now()]);
                }
            }

            $mailingMessages->each(function (MailingMessage $mailingMessage) use ($result, $failedEmails): void {
                $failedReason = $failedEmails[MailingContact::normalizeEmail($mailingMessage->email)] ?? null;
                $mailingMessage->update([
                    'status' => $failedReason ? 'failed' : 'accepted',
                    'unisender_job_id' => $result->jobId,
                    'response_hash' => $result->responseHash,
                    'request_profile' => $result->requestProfile->value,
                    'http_status_category' => $result->httpStatusCategory,
                    'safe_request_id' => $result->safeRequestId,
                    'safe_error_code' => $failedReason,
                    'safe_summary' => $failedReason ? 'recipient_rejected_safe' : 'provider_accepted',
                ]);
            });

            $this->recalculateStats($campaign);
            $this->stopIfThresholdsExceeded($campaign);

            if ($campaign->fresh()->status === 'paused_by_system') {
                break;
            }
        }

        $campaign = $campaign->fresh();
        if ($campaign->status === 'sending' && ! $campaign->recipients()->whereIn('status', ['pending', 'queued'])->exists()) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }

        $this->audit->log('campaign.started', MailingCampaign::class, $campaign->id, null, ['status' => $campaign->fresh()->status], userId: $userId);

        return $campaign->fresh();
    }

    public function pause(MailingCampaign|int $campaign, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $campaign->update(['status' => 'paused']);
        $this->audit->log('campaign.paused', MailingCampaign::class, $campaign->id, userId: $userId);

        return $campaign->fresh();
    }

    public function resume(MailingCampaign|int $campaign, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        if ($campaign->status !== 'paused') {
            throw new RuntimeException('Only paused campaigns can be resumed.');
        }

        $campaign->update(['status' => 'ready']);
        $this->audit->log('campaign.resumed', MailingCampaign::class, $campaign->id, userId: $userId);

        return $campaign->fresh();
    }

    public function cancel(MailingCampaign|int $campaign, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $campaign->update(['status' => 'cancelled']);
        $this->audit->log('campaign.cancelled', MailingCampaign::class, $campaign->id, userId: $userId);

        return $campaign->fresh();
    }

    public function cloneCampaign(MailingCampaign|int $campaign, ?int $userId = null): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $copy = $campaign->replicate(['status', 'started_at', 'completed_at', 'approved_by', 'approved_at']);
        $copy->name = $campaign->name.' copy';
        $copy->status = 'draft';
        $copy->created_by = $userId;
        $copy->updated_by = $userId;
        $copy->total_recipients = 0;
        $copy->accepted_count = 0;
        $copy->delivered_count = 0;
        $copy->opened_count = 0;
        $copy->unique_opened_count = 0;
        $copy->clicked_count = 0;
        $copy->unique_clicked_count = 0;
        $copy->unsubscribed_count = 0;
        $copy->soft_bounced_count = 0;
        $copy->hard_bounced_count = 0;
        $copy->spam_count = 0;
        $copy->failed_count = 0;
        $copy->save();

        foreach ($campaign->offerItems as $item) {
            $copy->offerItems()->create($item->replicate(['campaign_id'])->toArray());
        }

        $this->audit->log('campaign.duplicated', MailingCampaign::class, $campaign->id, null, ['copy_id' => $copy->id], userId: $userId);

        return $copy;
    }

    public function recalculateStats(MailingCampaign|int $campaign): MailingCampaign
    {
        $campaign = $this->campaign($campaign);
        $recipients = $campaign->recipients();
        $campaign->update([
            'total_recipients' => (clone $recipients)->count(),
            'accepted_count' => (clone $recipients)->whereIn('status', ['accepted', 'delivered', 'opened', 'clicked', 'unsubscribed'])->count(),
            'delivered_count' => (clone $recipients)->whereNotNull('delivered_at')->count(),
            'opened_count' => (clone $recipients)->sum('open_count'),
            'unique_opened_count' => (clone $recipients)->where('open_count', '>', 0)->count(),
            'clicked_count' => (clone $recipients)->sum('click_count'),
            'unique_clicked_count' => (clone $recipients)->where('click_count', '>', 0)->count(),
            'unsubscribed_count' => (clone $recipients)->whereNotNull('unsubscribed_at')->count(),
            'soft_bounced_count' => (clone $recipients)->whereNotNull('soft_bounced_at')->count(),
            'hard_bounced_count' => (clone $recipients)->whereNotNull('hard_bounced_at')->count(),
            'spam_count' => (clone $recipients)->whereNotNull('spam_at')->count(),
            'failed_count' => (clone $recipients)->where('status', 'failed')->count(),
        ]);

        return $campaign->fresh();
    }

    public function stopIfThresholdsExceeded(MailingCampaign|int $campaign): MailingCampaign
    {
        $campaign = $this->recalculateStats($campaign);
        $base = max(1, $campaign->accepted_count ?: $campaign->total_recipients);
        $spamRate = $campaign->spam_count / $base;
        $hardBounceRate = $campaign->hard_bounced_count / $base;
        $reasons = [];

        if ($spamRate > (float) config('services.mailings.stop_on_spam_rate', 0.002)) {
            $reasons[] = 'spam_rate='.$spamRate;
        }
        if ($hardBounceRate > (float) config('services.mailings.stop_on_hard_bounce_rate', 0.05)) {
            $reasons[] = 'hard_bounce_rate='.$hardBounceRate;
        }

        if ($reasons !== []) {
            $metadata = $campaign->metadata ?: [];
            $metadata['system_pause_reason'] = implode('; ', $reasons);
            $campaign->update(['status' => 'paused_by_system', 'metadata' => $metadata]);
            $this->audit->log('campaign.paused_by_system', MailingCampaign::class, $campaign->id, null, ['reason' => $metadata['system_pause_reason']]);
        }

        return $campaign->fresh();
    }

    public function validateBeforeSend(MailingCampaign $campaign, bool $requireRecipients = true): void
    {
        $errors = [];
        $html = $campaign->html_markup ?: $campaign->template?->html_markup;

        if (blank($campaign->subject)) {
            $errors[] = 'Subject is required.';
        }
        $fromEmail = $this->providerFromEmail();

        if (blank($fromEmail) || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid UNISENDER_GO_FROM_EMAIL is required.';
        }
        if ($fromEmail && $this->isPublicEmailDomain($fromEmail)) {
            $errors[] = 'UNISENDER_GO_FROM_EMAIL must use a corporate domain, not a public mailbox.';
        }
        if (blank($html)) {
            $errors[] = 'HTML markup is required.';
        }
        if ($requireRecipients && $campaign->type === 'mass_offer') {
            $hasManualRecipients = $campaign->recipients()->exists();
            if (! $campaign->contact_set_id && ! $hasManualRecipients) {
                $errors[] = 'Contact set is required for mass campaign.';
            } elseif ($campaign->contact_set_id && $this->recipientSets->countEligibleRecipients($campaign->contact_set_id, true) < 1) {
                $errors[] = 'No eligible recipients in selected contact set.';
            }
        }
        if (! (bool) config('services.unisender_go.enabled', false)) {
            $errors[] = 'Unisender Go provider is disabled.';
        }
        if (blank(config('services.unisender_go.api_key'))) {
            $errors[] = 'UNISENDER_GO_API_KEY is not configured.';
        }

        if ($html) {
            $errors = array_merge($errors, $this->renderer->validateEmailHtml($html));
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', array_unique($errors)));
        }
    }

    public function createTemplateVersion(MailingTemplate $template, ?string $changeNote = null, ?int $userId = null): MailingTemplateVersion
    {
        $next = ((int) $template->versions()->max('version_number')) + 1;

        return MailingTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_number' => $next,
            'subject' => $template->subject,
            'preheader' => $template->preheader,
            'editor_schema' => $template->editor_schema,
            'html_markup' => $template->html_markup,
            'plaintext' => $template->plaintext,
            'change_note' => $changeNote,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function ensureCampaignRecipients(MailingCampaign $campaign): void
    {
        if ($campaign->type === 'mass_offer' && $campaign->contact_set_id) {
            $contacts = $this->recipientSets->getEligibleRecipientsForCampaign($campaign->contact_set_id, true, 100000);
            foreach ($contacts as $contact) {
                MailingCampaignRecipient::query()->firstOrCreate(
                    ['campaign_id' => $campaign->id, 'normalized_email' => $contact->normalized_email],
                    [
                        'contact_id' => $contact->id,
                        'email' => $contact->email,
                        'status' => 'pending',
                        'substitutions' => $this->contactSubstitutions($contact, null),
                        'metadata' => ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
                    ]
                );
            }
        }

        $campaign->update(['total_recipients' => $campaign->recipients()->count()]);
    }

    private function buildMessage(MailingCampaign $campaign, Collection|\Illuminate\Support\Collection $recipients, bool $test = false): array
    {
        /** @var MailingCampaignRecipient $firstRecipient */
        $firstRecipient = $recipients->first();
        $firstContact = $firstRecipient?->contact ?: new MailingContact(['email' => $firstRecipient?->email ?: 'preview@example.ru']);
        $offerItems = $campaign->offerItems()->get()->all();
        $html = $this->renderer->renderCampaignHtml($campaign, $firstContact, $offerItems, $recipients->count() === 1 ? $firstRecipient : null);
        $plaintext = $this->renderer->renderPlaintext($campaign, $firstContact, $offerItems, $recipients->count() === 1 ? $firstRecipient : null);
        $idempotenceKey = 'campaign-'.$campaign->id.'-'.Str::uuid();

        $message = [
            'recipients' => $recipients->values()->map(function (MailingCampaignRecipient $recipient) use ($campaign) {
                $contact = $recipient->contact;

                return [
                    'email' => $recipient->email,
                    'substitutions' => $this->contactSubstitutions($contact, $recipient),
                    'metadata' => array_filter([
                        'campaign_id' => $campaign->id,
                        'campaign_recipient_id' => $recipient->id,
                        'contact_id' => $recipient->contact_id,
                    ], fn ($value) => $value !== null),
                ];
            })->all(),
            'body' => ['html' => $html, 'plaintext' => $plaintext],
            'subject' => ($test ? '[TEST] ' : '').$campaign->subject,
            'from_email' => $this->providerFromEmail(),
            'from_name' => $this->providerFromName(),
            'reply_to' => $this->providerReplyTo(),
            'track_links' => (bool) config('services.unisender_go.track_links', true) ? 1 : 0,
            'track_read' => (bool) config('services.unisender_go.track_read', true) ? 1 : 0,
            'template_id' => $campaign->template?->unisender_template_id,
            'global_substitutions' => ['campaign_name' => $campaign->name],
            'global_metadata' => ['campaign_id' => $campaign->id],
            'tags' => array_values(array_unique(array_filter(array_merge(['commercial_offer'], (array) $campaign->tags)))),
            'idempotence_key' => $idempotenceKey,
            'options' => array_filter([
                'unsubscribe_url' => $firstRecipient ? route('mailings.unsubscribe.show', $firstRecipient->unsubscribe_token) : null,
                'send_at' => $campaign->scheduled_at instanceof CarbonInterface ? $campaign->scheduled_at->toIso8601String() : null,
            ]),
        ];

        $this->assertRenderedMessageIsSafe($message);

        return $message;
    }

    private function createMailingMessages(MailingCampaign $campaign, Collection|\Illuminate\Support\Collection $recipients): \Illuminate\Support\Collection
    {
        return $recipients->map(fn (MailingCampaignRecipient $recipient) => MailingMessage::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'contact_id' => $recipient->contact_id,
            'email' => $recipient->email,
            'subject' => $campaign->subject,
            'status' => 'queued',
            'request_profile' => UnisenderRequestProfile::LegacyManual->value,
            'safe_summary' => 'queued_for_provider',
        ]));
    }

    private function attachMessageIdsToRecipientMetadata(array $recipients, \Illuminate\Support\Collection $messages): array
    {
        $messagesByEmail = $messages->keyBy(fn (MailingMessage $message) => MailingContact::normalizeEmail($message->email));

        return collect($recipients)->map(function (array $recipient) use ($messagesByEmail) {
            $message = $messagesByEmail->get(MailingContact::normalizeEmail($recipient['email'] ?? ''));
            if ($message) {
                $recipient['metadata']['mailing_message_id'] = $message->id;
            }

            return $recipient;
        })->all();
    }

    private function contactSubstitutions(?MailingContact $contact, ?MailingCampaignRecipient $recipient): array
    {
        $toName = trim(($contact?->first_name ?? '').' '.($contact?->last_name ?? ''));

        return [
            'to_name' => $toName,
            'greeting' => $toName !== '' ? 'Здравствуйте, '.$toName.'.' : 'Добрый день!',
            'first_name' => (string) $contact?->first_name,
            'last_name' => (string) $contact?->last_name,
            'company_name' => (string) $contact?->company_name,
            'unsubscribe_url' => $recipient ? route('mailings.unsubscribe.show', $recipient->unsubscribe_token) : '{{unsubscribe_url}}',
        ];
    }

    private function failedEmailMap(array $failedEmails): array
    {
        $map = [];
        foreach ($failedEmails as $entry) {
            if (is_string($entry)) {
                $map[MailingContact::normalizeEmail($entry)] = MailProviderSafeErrorCode::PermissionDenied->value;

                continue;
            }
            if (is_array($entry)) {
                $email = MailingContact::normalizeEmail((string) ($entry['email'] ?? $entry['address'] ?? ''));
                if ($email !== '') {
                    $map[$email] = MailProviderSafeErrorCode::PermissionDenied->value;
                }
            }
        }

        return $map;
    }

    private function requestHash(array $message): string
    {
        return hash('sha256', json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function assertRenderedMessageIsSafe(array $message): void
    {
        $html = (string) Arr::get($message, 'body.html', '');
        $plaintext = (string) Arr::get($message, 'body.plaintext', '');
        $errors = $this->renderer->validateEmailHtml($html);
        $payloadBytes = strlen($html)
            + strlen($plaintext)
            + strlen((string) ($message['subject'] ?? ''))
            + strlen(json_encode($message['recipients'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        if ($payloadBytes > MailingRenderer::MAX_MESSAGE_BYTES) {
            $errors[] = 'Rendered email payload is too large: '.$this->formatBytes($payloadBytes).'. Limit is '.$this->formatBytes(MailingRenderer::MAX_MESSAGE_BYTES).'. Remove embedded/base64 media or reduce content.';
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', array_unique($errors)));
        }
    }

    private function assertRecipientCanReceiveTest(string $email): void
    {
        $normalized = MailingContact::normalizeEmail($email);
        $suppression = MailingSuppression::query()
            ->where('normalized_email', $normalized)
            ->first();

        if ($suppression) {
            throw new RuntimeException(sprintf(
                'Recipient %s is blocked in local suppression list: cause=%s, source=%s. Remove suppression only if this block is known to be wrong.',
                $email,
                $suppression->cause ?: 'unknown',
                $suppression->source ?: 'unknown',
            ));
        }

        $contact = MailingContact::query()
            ->where('normalized_email', $normalized)
            ->first();

        if (! $contact) {
            return;
        }

        $reasons = array_filter([
            $contact->do_not_email ? 'do_not_email' : null,
            $contact->unsubscribed_at ? 'unsubscribed' : null,
            $contact->complained_at ? 'complained/spam' : null,
            $contact->hard_bounced_at ? 'hard_bounced' : null,
        ]);

        if ($reasons !== []) {
            throw new RuntimeException(sprintf(
                'Recipient %s is blocked locally: %s. This address will not be sent until the block is removed intentionally.',
                $email,
                implode(', ', $reasons),
            ));
        }
    }

    private function markRecipientSendFailed(MailingCampaignRecipient $recipient, RuntimeException $exception): void
    {
        $ambiguous = $exception instanceof MailProviderException && $exception->ambiguousAcceptance;
        $recipient->update([
            'status' => $ambiguous ? 'operator_review' : 'failed',
            'safe_error_code' => $this->safeErrorCode($exception),
            'safe_summary' => $ambiguous ? 'operator_review_no_resend' : 'provider_send_failed_safe',
        ]);
    }

    private function markMailingMessageFailed(MailingMessage $message, RuntimeException $exception): void
    {
        $providerException = $exception instanceof MailProviderException ? $exception : null;
        $ambiguous = $providerException?->ambiguousAcceptance === true;
        $message->update([
            'status' => $ambiguous ? 'operator_review' : 'failed',
            'response_hash' => $providerException?->responseHash,
            'http_status_category' => $providerException?->httpStatusCategory,
            'safe_request_id' => $providerException?->safeRequestId,
            'safe_error_code' => $this->safeErrorCode($exception),
            'safe_summary' => $ambiguous ? 'operator_review_no_resend' : 'provider_send_failed_safe',
            'ambiguous_acceptance_at' => $ambiguous ? now() : null,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 2).' MiB';
    }

    private function isPublicEmailDomain(string $email): bool
    {
        $domain = Str::lower(Str::after($email, '@'));

        return in_array($domain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'mail.ru', 'yandex.ru', 'ya.ru', 'rambler.ru'], true);
    }

    private function safeErrorCode(RuntimeException $exception): string
    {
        return $exception instanceof MailProviderException
            ? $exception->safeCode->value
            : MailProviderSafeErrorCode::ProcessingFailedSafe->value;
    }

    private function isTrackingConfigurationRejection(RuntimeException $exception): bool
    {
        return $exception instanceof MailProviderException
            && $exception->safeDetailCode === 'tracking_configuration_required';
    }

    private function campaign(MailingCampaign|int $campaign): MailingCampaign
    {
        return $campaign instanceof MailingCampaign ? $campaign->loadMissing(['template', 'offerItems', 'recipients.contact']) : MailingCampaign::query()->with(['template', 'offerItems', 'recipients.contact'])->findOrFail($campaign);
    }

    private function syncCampaignProviderSender(MailingCampaign $campaign): MailingCampaign
    {
        $payload = $this->providerSenderPayload();

        if (
            $campaign->from_email !== $payload['from_email']
            || $campaign->from_name !== $payload['from_name']
            || $campaign->reply_to !== $payload['reply_to']
        ) {
            $campaign->forceFill($payload)->save();
        }

        return $campaign->fresh(['template', 'offerItems', 'recipients.contact']);
    }

    private function providerSenderPayload(): array
    {
        return [
            'from_email' => $this->providerFromEmail(),
            'from_name' => $this->providerFromName(),
            'reply_to' => $this->providerReplyTo(),
        ];
    }

    private function providerFromEmail(): ?string
    {
        $email = MailingContact::normalizeEmail((string) config('services.unisender_go.from_email'));

        return $email !== '' ? $email : null;
    }

    private function providerFromName(): string
    {
        return trim((string) config('services.unisender_go.from_name')) ?: 'Pischeprom';
    }

    private function providerReplyTo(): ?string
    {
        $replyTo = MailingContact::normalizeEmail((string) config('services.unisender_go.reply_to'));

        return $replyTo !== '' ? $replyTo : $this->providerFromEmail();
    }
}
