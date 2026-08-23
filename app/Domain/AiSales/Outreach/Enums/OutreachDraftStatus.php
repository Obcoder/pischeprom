<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachDraftStatus: string
{
    case Draft = 'draft';
    case ReviewRequired = 'review_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Blocked = 'blocked';
    case Stale = 'stale';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
