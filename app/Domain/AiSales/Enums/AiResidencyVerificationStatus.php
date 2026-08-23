<?php

namespace App\Domain\AiSales\Enums;

enum AiResidencyVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Stale = 'stale';
    case Suspended = 'suspended';
}
