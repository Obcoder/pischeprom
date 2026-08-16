<?php

namespace App\Domain\AiSales\Enums;

enum UnitProductMatchStatus: string
{
    case Suggested = 'suggested';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Stale = 'stale';
}
