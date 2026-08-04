<?php

namespace App\Domain\AiPriceLists\Enums;

enum DocumentClass: string
{
    case PriceList = 'price_list';
    case NotPriceList = 'not_price_list';
    case Uncertain = 'uncertain';
}
