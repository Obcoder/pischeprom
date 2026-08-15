<?php

namespace App\Domain\AiSales\Enums;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case CommercialConfidential = 'commercial_confidential';
    case PersonalData = 'personal_data';
    case Secret = 'secret';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
