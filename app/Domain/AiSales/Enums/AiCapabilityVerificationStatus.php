<?php

namespace App\Domain\AiSales\Enums;

enum AiCapabilityVerificationStatus: string
{
    case Unknown = 'unknown';
    case Documented = 'documented';
    case SyntheticTested = 'synthetic_tested';
    case StagingApproved = 'staging_approved';
    case ProductionCanary = 'production_canary';
    case ProductionApproved = 'production_approved';
    case Suspended = 'suspended';

    public function verified(): bool
    {
        return in_array($this, [
            self::SyntheticTested,
            self::StagingApproved,
            self::ProductionCanary,
            self::ProductionApproved,
        ], true);
    }
}
