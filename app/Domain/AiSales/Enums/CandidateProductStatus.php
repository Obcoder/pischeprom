<?php

namespace App\Domain\AiSales\Enums;

enum CandidateProductStatus: string
{
    case Suggested = 'suggested';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Stale = 'stale';
}
