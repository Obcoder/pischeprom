<?php

namespace App\Domain\AiSales\Enums;

enum UnitProductMatchOrigin: string
{
    case Manual = 'manual';
    case Rule = 'rule';
    case Candidate = 'candidate';
    case FutureAi = 'future_ai';
}
