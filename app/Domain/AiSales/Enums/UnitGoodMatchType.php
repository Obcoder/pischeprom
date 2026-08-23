<?php

namespace App\Domain\AiSales\Enums;

enum UnitGoodMatchType: string
{
    case PotentialNeed = 'potential_need';
    case PotentialOffer = 'potential_offer';
    case CrossSell = 'cross_sell';
    case Unknown = 'unknown';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
