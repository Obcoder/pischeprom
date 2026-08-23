<?php

namespace App\Domain\AiSales\Enums;

enum ObservationVerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Contradicted = 'contradicted';
    case Stale = 'stale';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
