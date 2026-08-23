<?php

namespace App\Domain\AiSales\Enums;

enum UnitProductMatchType: string
{
    case PotentialNeed = 'potential_need';
    case PotentialOffer = 'potential_offer';
    case CrossSell = 'cross_sell';
    case Unknown = 'unknown';
}
