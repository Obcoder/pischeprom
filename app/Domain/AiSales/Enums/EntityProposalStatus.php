<?php

namespace App\Domain\AiSales\Enums;

enum EntityProposalStatus: string
{
    case ReviewRequired = 'review_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
