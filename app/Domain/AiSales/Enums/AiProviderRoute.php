<?php

namespace App\Domain\AiSales\Enums;

enum AiProviderRoute: string
{
    case LocalRu = 'local_ru';
    case ExternalSanitized = 'external_sanitized';

    public function contour(): AiProcessingContour
    {
        return match ($this) {
            self::LocalRu => AiProcessingContour::LocalRu,
            self::ExternalSanitized => AiProcessingContour::ExternalSanitized,
        };
    }
}
