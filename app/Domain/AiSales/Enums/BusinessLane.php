<?php

namespace App\Domain\AiSales\Enums;

enum BusinessLane: string
{
    case Sales = 'sales';
    case Procurement = 'procurement';
    case Logistics = 'logistics';
    case Service = 'service';
    case Internal = 'internal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Продажи',
            self::Procurement => 'Закупки',
            self::Logistics => 'Логистика',
            self::Service => 'Услуги',
            self::Internal => 'Внутренний',
        };
    }
}
