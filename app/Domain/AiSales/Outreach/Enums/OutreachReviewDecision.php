<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachReviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequired = 'changes_required';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
