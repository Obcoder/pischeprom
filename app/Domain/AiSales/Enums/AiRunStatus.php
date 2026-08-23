<?php

namespace App\Domain\AiSales\Enums;

enum AiRunStatus: string
{
    case Queued = 'queued';
    case Preparing = 'preparing';
    case PolicyCheck = 'policy_check';
    case Ready = 'ready';
    case Sent = 'sent';
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case BudgetExceeded = 'budget_exceeded';
    case BlockedByPolicy = 'blocked_by_policy';
    case BlockedByDlp = 'blocked_by_dlp';
    case BlockedByContour = 'blocked_by_contour';
    case ResidencyUnverified = 'residency_unverified';
    case ProviderUnavailable = 'provider_unavailable';

    public function terminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Cancelled,
            self::BudgetExceeded,
            self::BlockedByPolicy,
            self::BlockedByDlp,
            self::BlockedByContour,
            self::ResidencyUnverified,
            self::ProviderUnavailable,
        ], true);
    }
}
