<?php

namespace App\Domain\AiSales\Enums;

enum UnitGoodMatchStatus: string
{
    case Suggested = 'suggested';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Stale = 'stale';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
