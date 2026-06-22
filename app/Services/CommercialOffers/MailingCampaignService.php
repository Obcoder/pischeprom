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

        try {
            $result = $this->client->sendEmail($message);
        } catch (RuntimeException $exception) {
            if (! $this->isTrackingDomainConfigurationError($exception)) {
                $this->markRecipientSendFailed($recipient, $exception);

                throw new RuntimeException($this->testSendFailureMessage($exception, $email), previous: $exception);
            }

            Log::warning('Retrying Unisender Go test send without tracking because tracking domain is not configured', [
                'provider' => 'unisender_go',
                'campaign_id' => $campaign->id,
                'recipient_domain' => Str::after($email, '@'),
            ]);

            $message['track_read'] = 0;
            $message['track_links'] = 0;
            $message['global_metadata']['tracking_disabled_for_test'] = true;

            try {
                $retryResult = $this->client->sendEmail($message);
            } catch (RuntimeException $retryException) {
                $this->markRecipientSendFailed($recipient, $retryException);

                throw new RuntimeException($this->testSendFailureMessage($retryException, $email), previous: $retryException);
            }

            $result = new UnisenderSendResult(
                successful: $retryResult->successful,
                jobId: $retryResult->jobId,
                response: $retryResult->response + [
                    'tracking_disabled_for_test' => true,
                    'warning' => 'Tracking was disabled for this test send because Unisender requires a custom backend or tracking domain.',
                ],
                failedEmails: $retryResult->failedEmails,
                error: $exception->getMessage(),
            );
        }

        $recipient->update([
            'status' => 'accepted',
            'sent_at' => now(),
            'unisender_job_id' => $result->jobId,
            'idempotence_key' => $message['idempotence_key'],
        ]);

        MailingMessage::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'contact_id' => $contact->id,
            'email' => $email,
            'subject' => '[TEST] '.$campaign->subject,
            'status' => 'accepted',
            'unisender_job_id' => $result->jobId,
            'request_payload' => $this->safeRequestPayload($message),
            'response_payload' => $result->response,
            'failed_emails' => $result->failedEmails ?: null,
        ]);

        $this->audit->log('campaign.test_sent', MailingCampaign::class, $campaign->id, null, ['email' => $email, 'job_id' => $result->jobId], userId: $userId);

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

        if (in_array($campaign->status, ['sending', 'completed', 'cancelled'], true)) {
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
            $mailingMessages = $this->createMailingMessages($campaign, $chunk, $message);
            $message['recipients'] = $this->attachMessageIdsToRecipientMetadata($message['recipients'], $mailingMessages);

            try {
                $result = $this->client->sendEmail($message);
            } catch (RuntimeException $exception) {
                $campaign->update(['status' => 'failed']);
                $chunk->each(fn (MailingCampaignRecipient $recipient) => $recipient->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]));
                $mailingMessages->each->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
                Log::error('Unisender campaign send failed', ['campaign_id' => $campaign->id, 'error' => $exception->getMessage()]);
                throw $exception;
            }

            $failedEmails = $this->failedEmailMap($result->failedEmails);
            foreach ($chunk as $recipient) {
                $failedReason = $failedEmails[$recipient->normalized_email] ?? null;
                $recipient->update([
                    'status' => $failedReason ? 'failed' : 'accepted',
                    'failure_reason' => $failedReason,
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
                    'response_payload' => $result->response,
                    'failed_emails' => $result->failedEmails ?: null,
                    'error_message' => $failedReason,
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
        if ($html && ! str_contains($html, '{{unsubscribe_url}}') && ! str_contains($html, 'unsubscribe')) {
            $errors[] = 'Unsubscribe block or {{unsubscribe_url}} variable is required.';
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

    private function createMailingMessages(MailingCampaign $campaign, Collection|\Illuminate\Support\Collection $recipients, array $message): \Illuminate\Support\Collection
    {
        return $recipients->map(fn (MailingCampaignRecipient $recipient) => MailingMessage::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'contact_id' => $recipient->contact_id,
            'email' => $recipient->email,
            'subject' => $campaign->subject,
            'status' => 'queued',
            'request_payload' => $this->safeRequestPayload($message),
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
                $map[MailingContact::normalizeEmail($entry)] = 'Rejected by provider.';

                continue;
            }
            if (is_array($entry)) {
                $email = MailingContact::normalizeEmail((string) ($entry['email'] ?? $entry['address'] ?? ''));
                if ($email !== '') {
                    $map[$email] = (string) ($entry['message'] ?? $entry['reason'] ?? $entry['error'] ?? 'Rejected by provider.');
                }
            }
        }

        return $map;
    }

    private function safeRequestPayload(array $message): array
    {
        return [
            'subject' => $message['subject'] ?? null,
            'from_email' => $message['from_email'] ?? null,
            'recipients_count' => count($message['recipients'] ?? []),
            'html_bytes' => strlen((string) Arr::get($message, 'body.html', '')),
            'plaintext_bytes' => strlen((string) Arr::get($message, 'body.plaintext', '')),
            'track_links' => $message['track_links'] ?? null,
            'track_read' => $message['track_read'] ?? null,
            'template_id' => $message['template_id'] ?? null,
            'global_metadata' => $message['global_metadata'] ?? null,
            'tags' => $message['tags'] ?? null,
            'idempotence_key' => $message['idempotence_key'] ?? null,
        ];
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
        $recipient->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
        ]);
    }

    private function testSendFailureMessage(RuntimeException $exception, string $email): string
    {
        $message = $exception->getMessage();
        $lower = Str::lower($message);

        if (str_contains($lower, 'no valid recipients')) {
            return $message.' Attempted recipient: '.$email.'. Sender: '.$this->providerFromEmail().'. Check Unisender suppression/blocklist, free tier checked recipients/domains, recipient validity, and confirmed sender domain.';
        }

        if (str_contains($lower, 'message size limits') || str_contains($lower, 'exceeded google')) {
            return $message.' Rendered email was rejected by recipient server because it is too large. Remove embedded/base64 media, video, or reduce offer content.';
        }

        return $message;
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

    private function isTrackingDomainConfigurationError(RuntimeException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'tracking domain')
            || str_contains($message, 'custom backend domain');
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
