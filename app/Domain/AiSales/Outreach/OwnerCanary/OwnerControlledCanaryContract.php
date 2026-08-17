<?php

namespace App\Domain\AiSales\Outreach\OwnerCanary;

use App\Models\UnitProductMatch;

final class OwnerControlledCanaryContract
{
    public const SCENARIO = 'owner_controlled_broccoli_dispatch_v1';

    public const UNIT_NAME = 'Тестовая фабрика «Синтетика»';

    public const PRODUCT_NAME = 'Брокколи';

    public const SUBJECT = '[CANARY] Пищепром-Сервер — тест контролируемой отправки';

    public const PERMISSION_HOURS = 23;

    /** @return array<string, mixed> */
    public function content(UnitProductMatch $match): array
    {
        return [
            'subject' => self::SUBJECT,
            'greeting' => 'Здравствуйте!',
            'introduction' => 'Это одно контролируемое техническое тестовое сообщение для mailbox владельца.',
            'value_proposition' => 'Сценарий использует только вымышленное досье и синтетический продукт «Брокколи». Данные клиентов и поставщиков не используются.',
            'evidence_points' => [
                'Проверяется только защищённый reviewed outreach dispatch и нормализованный статус доставки.',
            ],
            'call_to_action' => 'Никаких действий и приобретений не требуется. Ответ нужен только при отдельно согласованной проверке маршрутизации ответа.',
            'closing' => 'Технический canary ПИЩЕПРОМ-СЕРВЕР',
            'claims' => [[
                'type' => 'product_relevance',
                'text' => 'В техническом сценарии указан синтетический продукт «Брокколи».',
                'evidence_type' => 'unit_product_match',
                'evidence_reference' => (string) $match->evidence_reference,
                'evidence_hash' => (string) $match->evidence_hash,
            ]],
        ];
    }
}
