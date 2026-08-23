<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachRevalidationCheckpoint: string
{
    case Prepare = 'prepare';
    case Queue = 'queue';
    case Worker = 'worker';
    case EligibilityPreview = 'eligibility_preview';
}
