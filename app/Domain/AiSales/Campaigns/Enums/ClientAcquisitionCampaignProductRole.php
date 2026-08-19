<?php

namespace App\Domain\AiSales\Campaigns\Enums;

enum ClientAcquisitionCampaignProductRole: string
{
    case Primary = 'primary';
    case Additional = 'additional';
    case Exclude = 'exclude';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
