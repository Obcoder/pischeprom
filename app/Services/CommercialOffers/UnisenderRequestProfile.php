<?php

namespace App\Services\CommercialOffers;

enum UnisenderRequestProfile: string
{
    case LegacyManual = 'legacy_manual';
    case OutreachZeroRetry = 'outreach_zero_retry';

    public function transportRetries(): int
    {
        return match ($this) {
            self::LegacyManual => 2,
            self::OutreachZeroRetry => 0,
        };
    }

    public function queueTries(): int
    {
        return 1;
    }
}
