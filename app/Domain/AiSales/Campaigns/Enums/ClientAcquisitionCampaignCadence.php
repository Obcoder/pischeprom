<?php

namespace App\Domain\AiSales\Campaigns\Enums;

enum ClientAcquisitionCampaignCadence: string
{
    case Manual = 'manual';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
