<?php

namespace App\Domain\AiSales\Enums;

enum ProspectingJobStatus: string
{
    case Draft = 'draft';
    case ReviewRequired = 'review_required';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
