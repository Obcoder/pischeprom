<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingEvent;
use App\Models\MailingLink;
use App\Models\MailingSuppression;
use App\Models\MailingWebhookCall;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnisenderWebhookService
{
    public function __construct(
        private readonly UnisenderGoClient $client,
        private readonly MailingCampaignService $campaigns,
    ) {}

    public function handleRawBody(string $rawBody, ?MailingWebhookCall $call = null): int
    {
        $payload = $this->client->parseWebhook($rawBody);
        $count = $this->handlePayload($payload);

        if ($call) {
            $call->update([
                'parsed_payload' => $payload->payload,
                'events_count' => $count,
                'processed_at' => now(),
            ]);
        }

        return $count;
    }

    public function handlePayload(UnisenderWebhookPayload $payload): int
    {
        $processed = 0;
        $campaignIds = [];

        foreach ($payload->events as $event) {
            $created = $this->processEvent($event);
            if ($created) {
                $processed++;
                if ($created->campaign_id) {
                    $campaignIds[$created->campaign_id] = true;
                }
            }
        }

        foreach (array_keys($campaignIds) as $campaignId) {
            $this->campaigns->stopIfThresholdsExceeded((int) $campaignId);
        }

        return $processed;
    }

    public function unsubscribeRecipient(MailingCampaignRecipient $recipient, string $source = 'local', ?int $userId = null): void
    {
        $now = now();
        $contact = $recipient->contact ?: MailingContact::query()->where('normalized_email', $recipient->normalized_email)->first();

        $recipient->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => $recipient->unsubscribed_at ?: $now,
        ]);

        if ($contact) {
            $contact->update([
                'unsubscribed_at' => $contact->unsubscribed_at ?: $now,
                'do_not_email' => true,
            ]);
        }

        $this->suppress($recipient->email, 'unsubscribed', $source, 'Local unsubscribe page', $userId);

        if ($recipient->campaign_id) {
            $this->campaigns->recalculateStats($recipient->campaign_id);
        }
    }

    private function processEvent(array $event): ?MailingEvent
    {
        $eventName = (string) ($event['event_name'] ?? $event['name'] ?? 'unknown');
        $data = (array) ($event['event_data'] ?? $event['data'] ?? $event);
        $status = (string) ($data['status'] ?? $data['email_status'] ?? ($eventName === 'transactional_spam_block' ? 'spam_block' : ''));
        $jobId = (string) ($data['job_id'] ?? $data['jobId'] ?? '');
        $metadata = (array) ($data['metadata'] ?? $data['user_metadata'] ?? []);
        $email = MailingContact::normalizeEmail((string) ($data['email'] ?? Arr::get($data, 'recipient.email') ?? ''));
        $url = (string) ($data['url'] ?? '');
        $eventTime = $this->eventTime($data['event_time'] ?? $data['timestamp'] ?? $event['event_time'] ?? null);
        $recipient = $this->findRecipient($metadata, $jobId, $email);
        $contact = $recipient?->contact ?: $this->findContact($metadata, $email);
        $campaignId = $recipient?->campaign_id ?: (int) ($metadata['campaign_id'] ?? 0) ?: null;
        $fingerprint = $this->fingerprint($jobId, $email, $status ?: $eventName, $eventTime?->toIso8601String(), $url, $recipient?->id);

        $mailingEvent = MailingEvent::query()->firstOrCreate(
            ['event_fingerprint' => $fingerprint],
            [
                'provider' => 'unisender_go',
                'campaign_id' => $campaignId,
                'campaign_recipient_id' => $recipient?->id,
                'contact_id' => $contact?->id,
                'unisender_job_id' => $jobId ?: null,
                'email' => $email ?: null,
                'event_name' => $eventName,
                'status' => $status ?: null,
                'event_time' => $eventTime,
                'url' => $url ?: null,
                'delivery_status' => (string) ($data['delivery_status'] ?? Arr::get($data, 'delivery_info.status') ?? '') ?: null,
                'destination_response' => (string) ($data['destination_response'] ?? Arr::get($data, 'delivery_info.destination_response') ?? '') ?: null,
                'user_agent' => (string) ($data['user_agent'] ?? Arr::get($data, 'client_info.user_agent') ?? '') ?: null,
                'ip' => (string) ($data['ip'] ?? Arr::get($data, 'client_info.ip') ?? '') ?: null,
                'country' => (string) ($data['country'] ?? Arr::get($data, 'client_info.country') ?? '') ?: null,
                'city' => (string) ($data['city'] ?? Arr::get($data, 'client_info.city') ?? '') ?: null,
                'sender_ip' => (string) ($data['sender_ip'] ?? Arr::get($data, 'delivery_info.sender_ip') ?? '') ?: null,
                'metadata' => $metadata ?: null,
                'payload' => $event,
                'created_at' => now(),
            ]
        );

        if (! $mailingEvent->wasRecentlyCreated) {
            return null;
        }

        Log::info('Unisender Go webhook event', [
            'provider' => 'unisender_go',
            'event_name' => $eventName,
            'status' => $status,
            'event_fingerprint' => $fingerprint,
            'job_id' => $jobId ?: null,
        ]);

        $this->applyStatus($status, $recipient, $contact, $eventTime ?: now(), $url, $data);

        if ($campaignId) {
            $this->campaigns->recalculateStats((int) $campaignId);
        }

        return $mailingEvent;
    }

    private function applyStatus(string $status, ?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time, string $url, array $data): void
    {
        if (! $recipient && ! $contact) {
            return;
        }

        match ($status) {
            'delivered' => $this->delivered($recipient, $time, $data),
            'opened' => $this->opened($recipient, $contact, $time),
            'clicked' => $this->clicked($recipient, $contact, $time, $url),
            'unsubscribed' => $this->unsubscribed($recipient, $contact, $time),
            'soft_bounced' => $this->softBounced($recipient, $contact, $time, $data),
            'hard_bounced' => $this->hardBounced($recipient, $contact, $time, $data),
            'spam' => $this->spam($recipient, $contact, $time),
            default => null,
        };
    }

    private function delivered(?MailingCampaignRecipient $recipient, Carbon $time, array $data): void
    {
        $recipient?->update([
            'status' => $this->maxStatus($recipient->status, 'delivered'),
            'delivered_at' => $recipient->delivered_at ?: $time,
            'delivery_info' => $data['delivery_info'] ?? $recipient->delivery_info,
        ]);
    }

    private function opened(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        $recipient?->update([
            'status' => $this->maxStatus($recipient->status, 'opened'),
            'first_opened_at' => $recipient->first_opened_at ?: $time,
            'last_opened_at' => $time,
            'open_count' => $recipient->open_count + 1,
        ]);
        $contact?->update(['last_opened_at' => $time]);
    }

    private function clicked(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time, string $url): void
    {
        $recipient?->update([
            'status' => $this->maxStatus($recipient->status, 'clicked'),
            'first_clicked_at' => $recipient->first_clicked_at ?: $time,
            'last_clicked_at' => $time,
            'click_count' => $recipient->click_count + 1,
            'last_clicked_url' => $url ?: $recipient->last_clicked_url,
        ]);
        $contact?->update(['last_clicked_at' => $time]);

        if ($recipient && $url !== '') {
            MailingLink::query()
                ->where('campaign_id', $recipient->campaign_id)
                ->where(function ($query) use ($url) {
                    $query->where('utm_url', $url)->orWhere('canonical_url', $url)->orWhere('original_url', $url);
                })
                ->increment('click_count');
        }
    }

    private function unsubscribed(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        $recipient?->update(['status' => 'unsubscribed', 'unsubscribed_at' => $time]);
        $contact?->update(['unsubscribed_at' => $contact->unsubscribed_at ?: $time, 'do_not_email' => true]);
        $this->suppress($recipient?->email ?: $contact?->email, 'unsubscribed', 'webhook', 'Unisender unsubscribe webhook');
    }

    private function softBounced(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time, array $data): void
    {
        $recipient?->update([
            'status' => 'soft_bounced',
            'soft_bounced_at' => $time,
            'failure_reason' => (string) ($data['reason'] ?? $data['message'] ?? $recipient->failure_reason),
            'delivery_info' => $data['delivery_info'] ?? $recipient->delivery_info,
        ]);

        if ($contact) {
            $contact->update([
                'soft_bounced_at' => $time,
                'soft_bounce_count' => $contact->soft_bounce_count + 1,
            ]);

            if ($contact->fresh()->soft_bounce_count >= 3) {
                $this->suppress($contact->email, 'temporary_unavailable', 'webhook', '3 soft bounces');
            }
        }
    }

    private function hardBounced(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time, array $data): void
    {
        $recipient?->update([
            'status' => 'hard_bounced',
            'hard_bounced_at' => $time,
            'failure_reason' => (string) ($data['reason'] ?? $data['message'] ?? $recipient->failure_reason),
            'delivery_info' => $data['delivery_info'] ?? $recipient->delivery_info,
        ]);
        $contact?->update(['hard_bounced_at' => $contact->hard_bounced_at ?: $time, 'do_not_email' => true]);
        $this->suppress($recipient?->email ?: $contact?->email, 'permanent_unavailable', 'webhook', 'Hard bounce');
    }

    private function spam(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        $recipient?->update(['status' => 'spam', 'spam_at' => $time]);
        $contact?->update(['complained_at' => $contact->complained_at ?: $time, 'do_not_email' => true]);
        $this->suppress($recipient?->email ?: $contact?->email, 'complained', 'webhook', 'Spam complaint');
    }

    private function suppress(?string $email, string $cause, string $source, ?string $note = null, ?int $userId = null): void
    {
        $email = MailingContact::normalizeEmail($email);
        if ($email === '') {
            return;
        }

        MailingSuppression::query()->updateOrCreate(
            ['normalized_email' => $email],
            ['email' => $email, 'cause' => $cause, 'source' => $source, 'note' => $note, 'created_by' => $userId]
        );

        try {
            $this->client->setSuppression($email, $cause);
        } catch (Throwable $exception) {
            Log::warning('Unisender suppression sync failed', [
                'provider' => 'unisender_go',
                'cause' => $cause,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function findRecipient(array $metadata, string $jobId, string $email): ?MailingCampaignRecipient
    {
        if (! empty($metadata['campaign_recipient_id'])) {
            $recipient = MailingCampaignRecipient::query()->find((int) $metadata['campaign_recipient_id']);
            if ($recipient) {
                return $recipient;
            }
        }

        return MailingCampaignRecipient::query()
            ->when($jobId !== '', fn ($query) => $query->where('unisender_job_id', $jobId))
            ->when($email !== '', fn ($query) => $query->where('normalized_email', $email))
            ->latest('id')
            ->first();
    }

    private function findContact(array $metadata, string $email): ?MailingContact
    {
        if (! empty($metadata['contact_id'])) {
            $contact = MailingContact::query()->find((int) $metadata['contact_id']);
            if ($contact) {
                return $contact;
            }
        }

        return $email !== '' ? MailingContact::query()->where('normalized_email', $email)->first() : null;
    }

    private function eventTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function fingerprint(string $jobId, string $email, string $status, ?string $eventTime, string $url, ?int $recipientId): string
    {
        return hash('sha256', implode('|', [
            'unisender_go',
            $jobId,
            $email,
            $status,
            $eventTime,
            $url,
            (string) $recipientId,
        ]));
    }

    private function maxStatus(?string $current, string $next): string
    {
        $terminal = ['unsubscribed', 'hard_bounced', 'spam'];
        if (in_array((string) $current, $terminal, true)) {
            return (string) $current;
        }

        $rank = ['pending' => 0, 'queued' => 1, 'sent' => 2, 'accepted' => 3, 'delivered' => 4, 'opened' => 5, 'clicked' => 6];

        return ($rank[$next] ?? 0) >= ($rank[$current] ?? 0) ? $next : (string) $current;
    }
}
