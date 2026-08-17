<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum CommunicationSuppressionReason: string
{
    case DoNotContact = 'do_not_contact';
    case Unsubscribed = 'unsubscribed';
    case Complaint = 'complaint';
    case HardBounce = 'hard_bounce';
    case InvalidAddress = 'invalid_address';
    case LegalHold = 'legal_hold';
    case ManualBlock = 'manual_block';
    case Suppressed = 'suppressed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
