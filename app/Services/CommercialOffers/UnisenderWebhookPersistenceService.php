<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingCampaignRecipient;
use App\Models\MailingEvent;
use App\Models\MailingMessage;
use App\Models\MailingWebhookCall;
use Illuminate\Support\Facades\DB;

final class UnisenderWebhookPersistenceService
{
    public function persist(VerifiedUnisenderWebhookRequest $verified): PersistedUnisenderWebhook
    {
        return DB::transaction(function () use ($verified): PersistedUnisenderWebhook {
            $call = MailingWebhookCall::query()->firstOrCreate(
                ['request_hash' => $verified->requestHash],
                [
                    'provider' => 'unisender_go',
                    'auth_valid' => true,
                    'events_count' => count($verified->events),
                    'status' => $verified->events === [] ? 'no_events' : 'verified',
                    'safe_summary' => $verified->events === [] ? 'verified_without_events' : 'verified_in_memory',
                    'verified_at' => now(),
                    'processed_at' => $verified->events === [] ? now() : null,
                    'created_at' => now(),
                ],
            );

            if (! $call->wasRecentlyCreated) {
                $retryIds = $call->status === 'queue_failed'
                    ? $call->events()->whereNull('processed_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all()
                    : [];

                return new PersistedUnisenderWebhook(
                    webhookCallId: (int) $call->id,
                    eventIdsToQueue: $retryIds,
                    duplicateRequest: true,
                    acceptedEventCount: (int) $call->events_count,
                );
            }

            $eventIds = [];
            foreach ($verified->events as $event) {
                $relations = $this->resolveRelations($event);
                $mailingEvent = MailingEvent::query()->firstOrCreate(
                    ['event_fingerprint' => $event->fingerprint],
                    [
                        'webhook_call_id' => $call->id,
                        'provider' => 'unisender_go',
                        'provider_event_id' => $event->providerEventId,
                        'campaign_id' => $relations['campaign_id'],
                        'campaign_recipient_id' => $relations['campaign_recipient_id'],
                        'contact_id' => $relations['contact_id'],
                        'unisender_job_id' => $event->providerJobId,
                        'provider_message_id' => $event->providerMessageId,
                        'mailing_message_id' => $relations['mailing_message_id'],
                        'sending_id' => $relations['sending_id'],
                        'mail_message_id' => $relations['mail_message_id'],
                        'event_name' => $event->eventType,
                        'normalized_event_type' => $event->eventType,
                        'status' => $event->status,
                        'normalized_status' => $event->status,
                        'event_time' => $event->occurredAt,
                        'verified_at' => now(),
                        'safe_summary' => 'queued_for_processing',
                        'created_at' => now(),
                    ],
                );

                if ($mailingEvent->wasRecentlyCreated) {
                    $eventIds[] = (int) $mailingEvent->id;
                }
            }

            $call->update([
                'status' => match (true) {
                    $verified->events === [] => 'no_events',
                    $eventIds === [] => 'duplicate',
                    default => 'queued',
                },
                'safe_error_code' => $eventIds === [] && $verified->events !== []
                    ? MailProviderSafeErrorCode::DuplicateEvent->value
                    : null,
                'safe_summary' => $eventIds === [] && $verified->events !== []
                    ? 'all_events_already_accepted'
                    : ($verified->events === [] ? 'verified_without_events' : 'events_durably_persisted'),
                'processed_at' => $eventIds === [] ? now() : null,
            ]);

            return new PersistedUnisenderWebhook(
                webhookCallId: (int) $call->id,
                eventIdsToQueue: $eventIds,
                duplicateRequest: false,
                acceptedEventCount: count($verified->events),
            );
        });
    }

    private function resolveRelations(NormalizedUnisenderEvent $event): array
    {
        $mailingMessage = $event->mailingMessageId
            ? MailingMessage::query()->find($event->mailingMessageId)
            : null;
        $recipient = $event->campaignRecipientId
            ? MailingCampaignRecipient::query()->find($event->campaignRecipientId)
            : null;

        if (! $recipient && $mailingMessage?->campaign_recipient_id) {
            $recipient = MailingCampaignRecipient::query()->find($mailingMessage->campaign_recipient_id);
        }

        if (! $recipient && $event->providerJobId && $event->recipientEmail) {
            $recipient = MailingCampaignRecipient::query()
                ->where('unisender_job_id', $event->providerJobId)
                ->select(['id', 'campaign_id', 'contact_id', 'normalized_email'])
                ->latest('id')
                ->limit(500)
                ->get()
                ->first(fn (MailingCampaignRecipient $candidate): bool => hash_equals(
                    (string) $candidate->normalized_email,
                    $event->recipientEmail,
                ));
        }

        $contactId = $recipient?->contact_id;
        if (! $contactId && $event->contactId) {
            $contactId = DB::table('mailing_contacts')->where('id', $event->contactId)->value('id');
        }

        return [
            'campaign_id' => $recipient?->campaign_id ?: $mailingMessage?->campaign_id,
            'campaign_recipient_id' => $recipient?->id,
            'contact_id' => $contactId ? (int) $contactId : null,
            'mailing_message_id' => $mailingMessage?->id,
            'sending_id' => $this->existingId('sendings', $event->sendingId),
            'mail_message_id' => $this->existingId('mail_messages', $event->mailMessageId),
        ];
    }

    private function existingId(string $table, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return DB::table($table)->where('id', $id)->exists() ? $id : null;
    }
}
