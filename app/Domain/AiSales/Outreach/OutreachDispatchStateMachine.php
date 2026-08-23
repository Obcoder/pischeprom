<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Models\OutreachDispatch;

final class OutreachDispatchStateMachine
{
    private const RANK = [
        'prepared' => 10,
        'review_required' => 11,
        'ready' => 20,
        'queue_pending' => 30,
        'queued' => 40,
        'provider_accepted' => 50,
        'sent' => 60,
        'soft_bounced' => 65,
        'delivered' => 70,
        'replied' => 80,
        'hard_bounced' => 90,
        'unsubscribed' => 100,
        'complained' => 110,
    ];

    public function transition(OutreachDispatch $dispatch, OutreachDispatchState $next, string $safeSummary): OutreachDispatch
    {
        $current = $dispatch->state;
        if ($current === $next) {
            return $dispatch;
        }

        $preProviderTerminal = [
            OutreachDispatchState::Cancelled,
            OutreachDispatchState::Expired,
            OutreachDispatchState::Failed,
            OutreachDispatchState::Blocked,
        ];
        if (in_array($current, $preProviderTerminal, true)) {
            return $dispatch;
        }

        $allowed = $next === OutreachDispatchState::AmbiguousAcceptance
            && in_array($current, [OutreachDispatchState::QueuePending, OutreachDispatchState::Queued], true)
            ? true
            : (in_array($next, $preProviderTerminal, true)
            && ! in_array($current, [
                OutreachDispatchState::ProviderAccepted, OutreachDispatchState::Sent,
                OutreachDispatchState::Delivered, OutreachDispatchState::HardBounced,
                OutreachDispatchState::Complained, OutreachDispatchState::Unsubscribed,
                OutreachDispatchState::Replied, OutreachDispatchState::AmbiguousAcceptance,
            ], true)
            ? true
            : ($current === OutreachDispatchState::AmbiguousAcceptance
            ? in_array($next, [
                OutreachDispatchState::ProviderAccepted, OutreachDispatchState::Sent,
                OutreachDispatchState::Delivered, OutreachDispatchState::HardBounced,
                OutreachDispatchState::Unsubscribed, OutreachDispatchState::Complained,
                OutreachDispatchState::Replied,
            ], true)
            : (self::RANK[$next->value] ?? 0) >= (self::RANK[$current->value] ?? 0)));
        if (! $allowed) {
            return $dispatch;
        }

        $timestamps = match ($next) {
            OutreachDispatchState::ProviderAccepted => ['provider_accepted_at' => $dispatch->provider_accepted_at ?: now()],
            OutreachDispatchState::Queued => ['queued_at' => $dispatch->queued_at ?: now()],
            OutreachDispatchState::Sent => ['sent_at' => $dispatch->sent_at ?: now()],
            OutreachDispatchState::Delivered => ['delivered_at' => $dispatch->delivered_at ?: now()],
            OutreachDispatchState::Replied => ['replied_at' => $dispatch->replied_at ?: now()],
            OutreachDispatchState::Cancelled => ['cancelled_at' => $dispatch->cancelled_at ?: now()],
            OutreachDispatchState::Failed => ['failed_at' => $dispatch->failed_at ?: now()],
            OutreachDispatchState::AmbiguousAcceptance => ['ambiguous_acceptance_at' => $dispatch->ambiguous_acceptance_at ?: now()],
            default => [],
        };

        $dispatch->forceFill([
            'state' => $next,
            'safe_summary' => mb_substr($safeSummary, 0, 255),
            'lock_version' => $dispatch->lock_version + 1,
            ...$timestamps,
        ])->save();

        return $dispatch;
    }
}
