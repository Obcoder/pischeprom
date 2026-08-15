<?php

namespace App\Domain\AiSales\Enums;

enum UnitContextStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Paused => 'Приостановлен',
            self::Closed => 'Закрыт',
            self::Archived => 'Архивирован',
        };
    }
}
