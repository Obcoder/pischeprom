<?php

namespace App\Domain\AiSales\Enums;

enum AiProcessingContour: string
{
    case None = 'none';
    case LocalRu = 'local_ru';
    case ExternalSanitized = 'external_sanitized';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
