<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum CommunicationPermissionStatus: string
{
    case Unknown = 'unknown';
    case EvidenceRequired = 'evidence_required';
    case PendingReview = 'pending_review';
    case Granted = 'granted';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
