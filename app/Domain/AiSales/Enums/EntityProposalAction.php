<?php

namespace App\Domain\AiSales\Enums;

enum EntityProposalAction: string
{
    case Create = 'create';
    case LinkExisting = 'link_existing';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
