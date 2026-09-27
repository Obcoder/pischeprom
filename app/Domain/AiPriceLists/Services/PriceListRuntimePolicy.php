<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Exceptions\ExternalAiException;

final class PriceListRuntimePolicy
{
    public static function aiEnabled(): bool
    {
        return config('ai-price-lists.enabled', false) === true
            && config('ai-price-lists.ai.enabled', false) === true;
    }

    public static function assertAiEnabled(): void
    {
        if (! self::aiEnabled()) {
            throw new ExternalAiException('AI/OCR-обработка прайс-листов отключена. Доступны локальный разбор и ручная работа.', false, 'ai_disabled');
        }
    }

    public static function notificationsEnabled(): bool
    {
        return config('ai-price-lists.enabled', false) === true
            && config('ai-price-lists.notifications_enabled', false) === true;
    }
}
