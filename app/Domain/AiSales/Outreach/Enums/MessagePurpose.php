<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum MessagePurpose: string
{
    case AdvertisingOutreach = 'advertising_outreach';
    case ResponseToInquiry = 'response_to_inquiry';
    case Transactional = 'transactional';
    case RelationshipService = 'relationship_service';
    case Unknown = 'unknown';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
