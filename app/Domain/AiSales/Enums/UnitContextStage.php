<?php

namespace App\Domain\AiSales\Enums;

enum UnitContextStage: string
{
    case New = 'new';
    case Researching = 'researching';
    case Qualified = 'qualified';
    case ReviewRequired = 'review_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ConvertedEntityLinked = 'converted_entity_linked';
    case Active = 'active';
    case Inactive = 'inactive';
    case DoNotContact = 'do_not_contact';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Researching => 'Исследование',
            self::Qualified => 'Квалифицирован',
            self::ReviewRequired => 'Нужна проверка',
            self::Approved => 'Одобрен',
            self::Rejected => 'Отклонён',
            self::ConvertedEntityLinked => 'Entity привязана',
            self::Active => 'Активный',
            self::Inactive => 'Неактивный',
            self::DoNotContact => 'Не связываться',
            self::Archived => 'Архив',
        };
    }
}
