<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachGenerationOrigin: string
{
    case Manual = 'manual';
    case FakeStructured = 'fake_structured';
    case HumanEdit = 'human_edit';
    case FutureLiveAi = 'future_live_ai';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
