<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachFollowUpStatus: string
{
    case NotPlanned = 'not_planned';
    case Recommended = 'recommended';
    case DraftRequired = 'draft_required';
    case ReviewRequired = 'review_required';
    case ScheduledDisabled = 'scheduled_disabled';
    case CancelledReply = 'cancelled_reply';
    case CancelledSuppression = 'cancelled_suppression';
    case CancelledBounce = 'cancelled_bounce';
    case Expired = 'expired';
    case Completed = 'completed';
}
