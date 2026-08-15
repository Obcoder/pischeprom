<?php

namespace App\Domain\AiSales\Enums;

enum UnitAliasType: string
{
    case TradeName = 'trade_name';
    case LegalHint = 'legal_hint';
    case Brand = 'brand';
    case DomainName = 'domain_name';
    case FormerName = 'former_name';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
