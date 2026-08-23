<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachDispatchState: string
{
    case Prepared = 'prepared';
    case ReviewRequired = 'review_required';
    case Ready = 'ready';
    case QueuePending = 'queue_pending';
    case Queued = 'queued';
    case ProviderAccepted = 'provider_accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case SoftBounced = 'soft_bounced';
    case HardBounced = 'hard_bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';
    case Replied = 'replied';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case AmbiguousAcceptance = 'ambiguous_acceptance';

    public function terminalForNewWork(): bool
    {
        return in_array($this, [
            self::HardBounced,
            self::Complained,
            self::Unsubscribed,
            self::Replied,
            self::Cancelled,
            self::Expired,
            self::AmbiguousAcceptance,
        ], true);
    }
}
