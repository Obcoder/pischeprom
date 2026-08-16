<?php

namespace App\Domain\AiSales\Enums;

enum ProspectingCandidateStatus: string
{
    case PendingResolution = 'pending_resolution';
    case ExactExistingUnit = 'exact_existing_unit';
    case ProbableExistingReview = 'probable_existing_review';
    case NewUnitReview = 'new_unit_review';
    case ExistingUnitEnriched = 'existing_unit_enriched';
    case NewUnitCreated = 'new_unit_created';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Anonymized = 'anonymized';

    public function terminal(): bool
    {
        return in_array($this, [
            self::ExistingUnitEnriched,
            self::NewUnitCreated,
            self::Rejected,
            self::Expired,
            self::Anonymized,
        ], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
