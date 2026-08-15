<?php

namespace App\Domain\AiSales\Enums;

enum AiModelProfile: string
{
    case HighVolumeExtraction = 'high_volume_extraction';
    case StandardResearch = 'standard_research';
    case ComplexResearch = 'complex_research';
    case Validation = 'validation';
    case OutreachDrafting = 'outreach_drafting';
    case ReplyTriage = 'reply_triage';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
