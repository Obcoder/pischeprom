<?php

namespace App\Domain\AiSales\Enums;

enum ScoreFactorStatus: string
{
    case Applied = 'applied';
    case Unknown = 'unknown';
    case Proposed = 'proposed';
    case Rejected = 'rejected';
    case Capped = 'capped';
    case Blocked = 'blocked';
}
