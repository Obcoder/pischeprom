<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum CommunicationSuppressionScope: string
{
    case Endpoint = 'endpoint';
    case Domain = 'domain';
    case Unit = 'unit';
    case Context = 'context';
    case Global = 'global';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
