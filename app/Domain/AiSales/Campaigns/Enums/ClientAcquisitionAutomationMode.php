<?php

namespace App\Domain\AiSales\Campaigns\Enums;

enum ClientAcquisitionAutomationMode: string
{
    case Manual = 'manual';
    case Assisted = 'assisted';
    case AutonomousReviewed = 'autonomous_reviewed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
