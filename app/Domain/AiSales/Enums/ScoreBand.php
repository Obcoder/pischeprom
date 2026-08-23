<?php

namespace App\Domain\AiSales\Enums;

enum ScoreBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case Promising = 'promising';
    case High = 'high';
    case VeryHigh = 'very_high';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score <= 24 => self::Low,
            $score <= 49 => self::Medium,
            $score <= 69 => self::Promising,
            $score <= 84 => self::High,
            default => self::VeryHigh,
        };
    }
}
