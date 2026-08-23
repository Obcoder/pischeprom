<?php

namespace App\Domain\AiSales\Enums;

enum ProspectingChannelKind: string
{
    case Email = 'email';
    case Telephone = 'telephone';
    case Uri = 'uri';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
