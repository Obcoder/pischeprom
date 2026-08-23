<?php

namespace App\Domain\AiSales\Enums;

enum CandidateResolutionOutcome: string
{
    case ExactExisting = 'exact_existing';
    case ProbableExistingReview = 'probable_existing_review';
    case NewUnitAllowed = 'new_unit_allowed';
    case RejectedInvalid = 'rejected_invalid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
