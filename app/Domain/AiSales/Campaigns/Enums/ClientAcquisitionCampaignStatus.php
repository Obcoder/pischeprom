<?php

namespace App\Domain\AiSales\Campaigns\Enums;

enum ClientAcquisitionCampaignStatus: string
{
    case Draft = 'draft';
    case ReviewRequired = 'review_required';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Archived], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
