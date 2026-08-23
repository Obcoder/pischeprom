<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Models\MailingEvent;
use App\Models\OutreachDispatch;

final class OutreachNormalizedEventService
{
    public function __construct(
        private readonly OutreachFeatureGuard $features,
        private readonly OutreachDispatchStateMachine $states,
        private readonly CommunicationSuppressionService $suppressions,
        private readonly OutreachFollowUpCancellationService $followUps,
    ) {}

    public function apply(MailingEvent $event): bool
    {
        if (! $this->features->eventIngestionEnabled() || ! $event->sending_id) {
            return false;
        }

        $dispatch = OutreachDispatch::query()
            ->where('sending_id', $event->sending_id)
            ->lockForUpdate()
            ->first();
        if (! $dispatch) {
            return false;
        }

        $status = (string) $event->normalized_status;
        $next = match ($status) {
            'accepted' => OutreachDispatchState::ProviderAccepted,
            'sent' => OutreachDispatchState::Sent,
            'delivered' => OutreachDispatchState::Delivered,
            'soft_bounced' => OutreachDispatchState::SoftBounced,
            'hard_bounced' => OutreachDispatchState::HardBounced,
            'unsubscribed' => OutreachDispatchState::Unsubscribed,
            'complaint', 'spam' => OutreachDispatchState::Complained,
            default => null,
        };

        if ($next) {
            $this->states->transition($dispatch, $next, 'normalized_provider_event_'.$status);
        }
        if ($dispatch->sending) {
            $sendingStatus = match ($status) {
                'opened', 'clicked' => $dispatch->sending->status,
                default => match ($dispatch->state) {
                    OutreachDispatchState::ProviderAccepted => 'accepted',
                    OutreachDispatchState::Sent => 'sent',
                    OutreachDispatchState::Delivered => 'delivered',
                    OutreachDispatchState::SoftBounced => 'soft_bounced',
                    OutreachDispatchState::HardBounced => 'hard_bounced',
                    OutreachDispatchState::Unsubscribed => 'unsubscribed',
                    OutreachDispatchState::Complained => 'complained',
                    default => $dispatch->sending->status,
                },
            };
            $updates = ['status' => $sendingStatus, 'safe_summary' => 'normalized_provider_event_'.$status];
            if ($status === 'opened') {
                $updates['opened_at'] = $dispatch->sending->opened_at ?: ($event->event_time ?: now());
                $updates['opens_count'] = $dispatch->sending->opens_count + 1;
            }
            if ($status === 'clicked') {
                $updates['clicked_at'] = $dispatch->sending->clicked_at ?: ($event->event_time ?: now());
                $updates['click_count'] = $dispatch->sending->click_count + 1;
            }
            $dispatch->sending->forceFill($updates)->save();
        }

        $suppression = match ($status) {
            'hard_bounced' => CommunicationSuppressionReason::HardBounce,
            'unsubscribed' => CommunicationSuppressionReason::Unsubscribed,
            'complaint', 'spam' => CommunicationSuppressionReason::Complaint,
            default => null,
        };
        if ($suppression) {
            $this->suppressions->createSystemEndpointSuppression(
                $dispatch,
                $suppression,
                'verified_unisender_event',
                'mailing-event:'.$event->id,
            );
            $cancelState = $status === 'hard_bounced'
                ? OutreachFollowUpStatus::CancelledBounce
                : OutreachFollowUpStatus::CancelledSuppression;
            $this->followUps->cancel($dispatch, $cancelState, $status);
        }

        return true;
    }
}
