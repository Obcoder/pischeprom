<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachReviewType: string
{
    case Content = 'content';
    case Claims = 'claims';
    case Permission = 'permission';
    case Recipient = 'recipient';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
