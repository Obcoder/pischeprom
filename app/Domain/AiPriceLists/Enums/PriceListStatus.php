<?php

namespace App\Domain\AiPriceLists\Enums;

enum PriceListStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Validating = 'validating';
    case AwaitingClassification = 'awaiting_classification';
    case Extracting = 'extracting';
    case Ocr = 'ocr';
    case Normalizing = 'normalizing';
    case Matching = 'matching';
    case SupplierUnresolved = 'supplier_unresolved';
    case ReviewRequired = 'review_required';
    case ReadyToApply = 'ready_to_apply';
    case Applying = 'applying';
    case PartiallyApplied = 'partially_applied';
    case Applied = 'applied';
    case NotAPriceList = 'not_a_price_list';
    case UnsupportedFormat = 'unsupported_format';
    case Quarantined = 'quarantined';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Applied,
            self::NotAPriceList,
            self::Cancelled,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получен',
            self::Queued => 'В очереди',
            self::Validating => 'Проверка файла',
            self::AwaitingClassification => 'Нужно подтвердить тип',
            self::Extracting => 'Извлечение данных',
            self::Ocr => 'OCR',
            self::Normalizing => 'Нормализация',
            self::Matching => 'Сопоставление',
            self::SupplierUnresolved => 'Поставщик не определён',
            self::ReviewRequired => 'Требуется проверка',
            self::ReadyToApply => 'Готов к применению',
            self::Applying => 'Применяется',
            self::PartiallyApplied => 'Применён частично',
            self::Applied => 'Применён',
            self::NotAPriceList => 'Не прайс-лист',
            self::UnsupportedFormat => 'Формат не поддерживается',
            self::Quarantined => 'Карантин',
            self::Failed => 'Ошибка',
            self::Cancelled => 'Отменён',
        };
    }
}
