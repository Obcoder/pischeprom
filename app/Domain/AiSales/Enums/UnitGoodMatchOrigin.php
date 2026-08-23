<?php

namespace App\Domain\AiSales\Enums;

enum UnitGoodMatchOrigin: string
{
    case Manual = 'manual';
    case Rule = 'rule';
    case Candidate = 'candidate';
    case AiFuture = 'ai_future';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
