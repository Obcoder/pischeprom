<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingContact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use JsonException;
use Throwable;

final class UnisenderWebhookIngress
{
    public const MAX_ENCODED_BODY_BYTES = 262_144;

    public const MAX_EVENTS_PER_REQUEST = 100;

    public function __construct(
        private readonly UnisenderGoClient $client,
        private readonly SafeMailProviderIdentifier $identifiers,
    ) {}

    public function authenticate(string $rawBody): AuthenticatedUnisenderWebhookRequest
    {
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::MalformedPayload, 400);
        }

        if (! is_array($payload)) {
            throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::MalformedPayload, 400);
        }

        $groups = $payload['events_by_user'] ?? null;
        if (! is_array($groups)) {
            throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::MalformedPayload, 422);
        }

        $events = [];
        foreach ($groups as $group) {
            if (! is_array($group) || ! isset($group['events']) || ! is_array($group['events'])) {
                throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::MalformedPayload, 422);
            }

            foreach ($group['events'] as $event) {
                if (! is_array($event)) {
                    throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::MalformedPayload, 422);
                }

                if (count($events) >= self::MAX_EVENTS_PER_REQUEST) {
                    throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::PayloadTooLarge, 413);
                }

                $events[] = $event;
            }
        }

        if (! $this->client->verifyWebhookRawBodyWithPayload($rawBody, $payload)) {
            throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::InvalidSignature, 403);
        }

        return new AuthenticatedUnisenderWebhookRequest(hash('sha256', $rawBody), $events);
    }

    public function normalize(AuthenticatedUnisenderWebhookRequest $authenticated): VerifiedUnisenderWebhookRequest
    {
        $events = array_map(
            fn (array $event): NormalizedUnisenderEvent => $this->normalizeEvent($event),
            $authenticated->providerEvents(),
        );

        return new VerifiedUnisenderWebhookRequest($authenticated->requestHash, $events);
    }

    public function verifyAndNormalize(string $rawBody): VerifiedUnisenderWebhookRequest
    {
        return $this->normalize($this->authenticate($rawBody));
    }

    private function normalizeEvent(array $event): NormalizedUnisenderEvent
    {
        $providerEventName = mb_strtolower(trim((string) ($event['event_name'] ?? $event['name'] ?? '')));
        $data = $event['event_data'] ?? $event['data'] ?? $event;
        if (! is_array($data)) {
            throw new UnisenderWebhookRequestException(MailProviderSafeErrorCode::MalformedPayload, 422);
        }

        $metadata = $data['metadata'] ?? $data['user_metadata'] ?? [];
        $metadata = is_array($metadata) ? $metadata : [];
        $providerStatus = mb_strtolower(trim((string) (
            $data['status']
            ?? $data['email_status']
            ?? ($providerEventName === 'transactional_spam_block' ? 'spam_block' : '')
        )));

        $eventType = match ($providerEventName) {
            'transactional_email_status', 'email_status' => 'email_status',
            'transactional_spam_block', 'spam_block' => 'spam_block',
            default => 'unknown',
        };
        $status = match ($providerStatus) {
            'sent' => 'sent',
            'accepted' => 'accepted',
            'delivered' => 'delivered',
            'open', 'opened' => 'opened',
            'click', 'clicked' => 'clicked',
            'unsubscribe', 'unsubscribed' => 'unsubscribed',
            'soft_bounce', 'soft_bounced' => 'soft_bounced',
            'hard_bounce', 'hard_bounced' => 'hard_bounced',
            'spam', 'complaint', 'complained', 'spam_block' => 'spam',
            default => 'unknown',
        };

        $providerEventId = $this->safeProviderId(
            $event['event_id'] ?? $event['id'] ?? $data['event_id'] ?? $data['id'] ?? null
        );
        $providerJobId = $this->safeProviderId($data['job_id'] ?? $data['jobId'] ?? null);
        $providerMessageId = $this->safeProviderId(
            $data['message_id'] ?? $data['messageId'] ?? $data['email_id'] ?? null
        );
        $occurredAt = $this->eventTime(
            $data['event_time'] ?? $data['timestamp'] ?? $event['event_time'] ?? null
        );
        $campaignRecipientId = $this->positiveInt($metadata['campaign_recipient_id'] ?? null);
        $contactId = $this->positiveInt($metadata['contact_id'] ?? null);
        $mailingMessageId = $this->positiveInt($metadata['mailing_message_id'] ?? null);
        $sendingId = $this->positiveInt($metadata['sending_id'] ?? null);
        $mailMessageId = $this->positiveInt($metadata['mail_message_id'] ?? null);
        $recipientEmail = MailingContact::normalizeEmail((string) (
            $data['email'] ?? Arr::get($data, 'recipient.email') ?? ''
        ));
        $recipientEmail = filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) ? $recipientEmail : null;
        $recipientHash = $recipientEmail ? hash('sha256', $recipientEmail) : null;
        $url = trim((string) ($data['url'] ?? ''));
        $urlHash = $url === '' ? null : hash('sha256', $url);

        $fingerprintSource = $providerEventId
            ? ['provider' => 'unisender_go', 'provider_event_id' => $providerEventId]
            : [
                'provider' => 'unisender_go',
                'provider_job_id' => $providerJobId,
                'provider_message_id' => $providerMessageId,
                'event_type' => $eventType,
                'status' => $status,
                'occurred_at' => $occurredAt,
                'campaign_recipient_id' => $campaignRecipientId,
                'contact_id' => $contactId,
                'mailing_message_id' => $mailingMessageId,
                'sending_id' => $sendingId,
                'mail_message_id' => $mailMessageId,
                'recipient_hash' => $recipientHash,
                'url_hash' => $urlHash,
            ];

        return new NormalizedUnisenderEvent(
            fingerprint: hash('sha256', json_encode($fingerprintSource, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            eventType: $eventType,
            status: $status,
            providerEventId: $providerEventId,
            providerJobId: $providerJobId,
            providerMessageId: $providerMessageId,
            occurredAt: $occurredAt,
            campaignRecipientId: $campaignRecipientId,
            contactId: $contactId,
            mailingMessageId: $mailingMessageId,
            sendingId: $sendingId,
            mailMessageId: $mailMessageId,
            recipientEmail: $recipientEmail,
        );
    }

    private function safeProviderId(mixed $value): ?string
    {
        return $this->identifiers->normalize($value);
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/', $value) === 1)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function eventTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $time = is_numeric($value)
                ? CarbonImmutable::createFromTimestampUTC((int) $value)
                : CarbonImmutable::parse((string) $value);

            return $time->utc()->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
