<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachReplyTriageStatus: string
{
    case ReviewRequired = 'review_required';
    case FakeClassified = 'fake_classified';
    case Reviewed = 'reviewed';
}
