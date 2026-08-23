<?php

namespace App\Domain\AiSales\Enums;

enum ScoreConfidenceBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public static function fromConfidence(int $confidence): self
    {
        return match (true) {
            $confidence <= 39 => self::Low,
            $confidence <= 69 => self::Medium,
            default => self::High,
        };
    }
}
