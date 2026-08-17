<?php

namespace App\Domain\AiSales\Enums;

enum ScoreReviewStatus: string
{
    case NotReviewed = 'not_reviewed';
    case ReviewRequired = 'review_required';
    case Reviewed = 'reviewed';
    case Rejected = 'rejected';
}
