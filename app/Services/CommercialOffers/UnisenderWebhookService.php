<?php

namespace App\Services\CommercialOffers;

use App\Domain\AiSales\Outreach\OutreachNormalizedEventService;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingEvent;
use App\Models\MailingSuppression;
use App\Models\MailingWebhookCall;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnisenderWebhookService
{
    private const TERMINAL_STATUS_RANK = [
        'hard_bounced' => 80,
        'unsubscribed' => 90,
        'spam' => 100,
    ];

    public function __construct(
        private readonly UnisenderGoClient $client,
        private readonly MailingCampaignService $campaigns,
        private readonly OutreachNormalizedEventService $outreachEvents,
    ) {}

    /**
     * @param  list<int>  $eventIds
     */
    public function processStoredEventIds(array $eventIds): int
    {
        $processed = 0;

        foreach (array_slice(array_values(array_unique(array_map('intval', $eventIds))), 0, UnisenderWebhookIngress::MAX_EVENTS_PER_REQUEST) as $eventId) {
            if ($eventId > 0 && $this->processStoredEvent($eventId)) {
                $processed++;
            }
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

    private function processStoredEvent(int $eventId): bool
    {
        try {
            $result = DB::transaction(function () use ($eventId): array {
                $event = MailingEvent::query()->lockForUpdate()->find($eventId);
                if (! $event || $event->processed_at) {
                    return ['processed' => false, 'campaign_id' => null, 'webhook_call_id' => null];
                }

                if ($event->normalized_event_type === 'unknown' || $event->normalized_status === 'unknown') {
                    $event->update([
                        'processed_at' => now(),
                        'safe_error_code' => MailProviderSafeErrorCode::UnknownEvent->value,
                        'safe_summary' => 'unsupported_event_recorded_without_side_effects',
                    ]);

                    return [
                        'processed' => true,
                        'campaign_id' => null,
                        'webhook_call_id' => $event->webhook_call_id,
                    ];
                }

                $recipient = $event->campaign_recipient_id
                    ? MailingCampaignRecipient::query()->lockForUpdate()->find($event->campaign_recipient_id)
                    : null;
                $contact = $recipient?->contact
                    ?: ($event->contact_id ? MailingContact::query()->lockForUpdate()->find($event->contact_id) : null);

                $outreachApplied = $this->outreachEvents->apply($event);

                $this->applyStatus(
                    (string) $event->normalized_status,
                    $recipient,
                    $contact,
                    $event->event_time ?: now(),
                );

                $event->update([
                    'processed_at' => now(),
                    'safe_error_code' => null,
                    'safe_summary' => $recipient || $contact || $outreachApplied
                        ? 'normalized_event_applied'
                        : 'normalized_event_unmatched',
                ]);

                return [
                    'processed' => true,
                    'campaign_id' => $recipient?->campaign_id ?: $event->campaign_id,
                    'webhook_call_id' => $event->webhook_call_id,
                ];
            });
        } catch (Throwable) {
            $failedEvent = MailingEvent::query()->select(['id', 'webhook_call_id'])->find($eventId);
            MailingEvent::query()->whereKey($eventId)->whereNull('processed_at')->update([
                'processed_at' => now(),
                'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
                'safe_summary' => 'event_processing_failed_safe',
            ]);
            if ($failedEvent?->webhook_call_id) {
                MailingWebhookCall::query()->whereKey($failedEvent->webhook_call_id)->update([
                    'status' => 'processing_failed',
                    'processed_at' => now(),
                    'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
                    'safe_summary' => 'normalized_event_processing_failed_safe',
                ]);
            }
            Log::error('Unisender webhook event processing failed', [
                'provider' => 'unisender_go',
                'event_id' => $eventId,
                'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
            ]);

            return false;
        }

        if ($result['webhook_call_id']) {
            $this->completeWebhookCallIfFinished((int) $result['webhook_call_id']);
        }

        if ($result['campaign_id']) {
            $this->campaigns->recalculateStats((int) $result['campaign_id']);
            $this->campaigns->stopIfThresholdsExceeded((int) $result['campaign_id']);
        }

        return (bool) $result['processed'];
    }

    private function completeWebhookCallIfFinished(int $webhookCallId): void
    {
        if (MailingEvent::query()->where('webhook_call_id', $webhookCallId)->whereNull('processed_at')->exists()) {
            return;
        }

        MailingWebhookCall::query()->whereKey($webhookCallId)->update([
            'status' => 'processed',
            'processed_at' => now(),
            'safe_error_code' => null,
            'safe_summary' => 'all_normalized_events_processed',
        ]);
    }

    private function applyStatus(string $status, ?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        if (! $recipient && ! $contact) {
            return;
        }

        match ($status) {
            'sent', 'accepted' => $this->accepted($recipient, $status),
            'delivered' => $this->delivered($recipient, $time),
            'opened' => $this->opened($recipient, $contact, $time),
            'clicked' => $this->clicked($recipient, $contact, $time),
            'unsubscribed' => $this->unsubscribed($recipient, $contact, $time),
            'soft_bounced' => $this->softBounced($recipient, $contact, $time),
            'hard_bounced' => $this->hardBounced($recipient, $contact, $time),
            'spam' => $this->spam($recipient, $contact, $time),
            default => null,
        };
    }

    private function accepted(?MailingCampaignRecipient $recipient, string $status): void
    {
        if (! $recipient || $this->isTerminal($recipient->status)) {
            return;
        }

        $recipient->update(['status' => $this->maxStatus($recipient->status, $status)]);
    }

    private function delivered(?MailingCampaignRecipient $recipient, Carbon $time): void
    {
        if (! $recipient || $this->isTerminal($recipient->status)) {
            return;
        }

        $recipient->update([
            'status' => $this->maxStatus($recipient->status, 'delivered'),
            'delivered_at' => $recipient->delivered_at ?: $time,
        ]);
    }

    private function opened(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        if ($recipient && ! $this->isTerminal($recipient->status)) {
            $recipient->update([
                'status' => $this->maxStatus($recipient->status, 'opened'),
                'first_opened_at' => $recipient->first_opened_at ?: $time,
                'last_opened_at' => $time,
                'open_count' => $recipient->open_count + 1,
            ]);
            $contact?->update(['last_opened_at' => $time]);
        }
    }

    private function clicked(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        if ($recipient && ! $this->isTerminal($recipient->status)) {
            $recipient->update([
                'status' => $this->maxStatus($recipient->status, 'clicked'),
                'first_clicked_at' => $recipient->first_clicked_at ?: $time,
                'last_clicked_at' => $time,
                'click_count' => $recipient->click_count + 1,
            ]);
            $contact?->update(['last_clicked_at' => $time]);
        }
    }

    private function unsubscribed(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        $recipient?->update([
            'status' => $this->terminalStatus($recipient->status, 'unsubscribed'),
            'unsubscribed_at' => $recipient->unsubscribed_at ?: $time,
        ]);
        $contact?->update(['unsubscribed_at' => $contact->unsubscribed_at ?: $time, 'do_not_email' => true]);
        $this->suppress(
            $recipient?->email ?: $contact?->email,
            'unsubscribed',
            'webhook',
            'Unisender unsubscribe webhook',
            syncProvider: false,
        );
    }

    private function softBounced(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        if ($recipient && ! $this->isTerminal($recipient->status)) {
            $recipient->update([
                'status' => 'soft_bounced',
                'soft_bounced_at' => $time,
                'safe_error_code' => null,
                'safe_summary' => 'provider_soft_bounce',
            ]);
        }

        if ($contact) {
            $contact->update([
                'soft_bounced_at' => $time,
                'soft_bounce_count' => $contact->soft_bounce_count + 1,
            ]);

            if ($contact->fresh()->soft_bounce_count >= 3) {
                $this->suppress($contact->email, 'temporary_unavailable', 'webhook', '3 soft bounces', syncProvider: false);
            }
        }
    }

    private function hardBounced(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        $recipient?->update([
            'status' => $this->terminalStatus($recipient->status, 'hard_bounced'),
            'hard_bounced_at' => $recipient->hard_bounced_at ?: $time,
            'safe_error_code' => null,
            'safe_summary' => 'provider_hard_bounce',
        ]);
        $contact?->update(['hard_bounced_at' => $contact->hard_bounced_at ?: $time, 'do_not_email' => true]);
        $this->suppress(
            $recipient?->email ?: $contact?->email,
            'permanent_unavailable',
            'webhook',
            'Hard bounce',
            syncProvider: false,
        );
    }

    private function spam(?MailingCampaignRecipient $recipient, ?MailingContact $contact, Carbon $time): void
    {
        $recipient?->update([
            'status' => $this->terminalStatus($recipient->status, 'spam'),
            'spam_at' => $recipient->spam_at ?: $time,
        ]);
        $contact?->update(['complained_at' => $contact->complained_at ?: $time, 'do_not_email' => true]);
        $this->suppress(
            $recipient?->email ?: $contact?->email,
            'complained',
            'webhook',
            'Spam complaint',
            syncProvider: false,
        );
    }

    private function suppress(
        ?string $email,
        string $cause,
        string $source,
        ?string $note = null,
        ?int $userId = null,
        bool $syncProvider = true,
    ): void {
        $email = MailingContact::normalizeEmail($email);
        if ($email === '') {
            return;
        }

        MailingSuppression::query()->updateOrCreate(
            ['normalized_email' => $email],
            ['email' => $email, 'cause' => $cause, 'source' => $source, 'note' => $note, 'created_by' => $userId]
        );

        if (! $syncProvider) {
            return;
        }

        try {
            $this->client->setSuppression($email, $cause);
        } catch (MailProviderException $exception) {
            Log::warning('Unisender suppression sync failed', [
                'provider' => 'unisender_go',
                'cause' => $cause,
                'safe_error_code' => $exception->safeCode->value,
            ]);
        } catch (Throwable) {
            Log::warning('Unisender suppression sync failed', [
                'provider' => 'unisender_go',
                'cause' => $cause,
                'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
            ]);
        }
    }

    private function maxStatus(?string $current, string $next): string
    {
        if ($this->isTerminal($current)) {
            return (string) $current;
        }

        $rank = ['pending' => 0, 'queued' => 1, 'sent' => 2, 'accepted' => 3, 'delivered' => 4, 'opened' => 5, 'clicked' => 6];

        return ($rank[$next] ?? 0) >= ($rank[$current] ?? 0) ? $next : (string) $current;
    }

    private function terminalStatus(?string $current, string $next): string
    {
        $currentRank = self::TERMINAL_STATUS_RANK[(string) $current] ?? 0;
        $nextRank = self::TERMINAL_STATUS_RANK[$next] ?? 0;

        return $nextRank >= $currentRank ? $next : (string) $current;
    }

    private function isTerminal(?string $status): bool
    {
        return isset(self::TERMINAL_STATUS_RANK[(string) $status]);
    }
}
