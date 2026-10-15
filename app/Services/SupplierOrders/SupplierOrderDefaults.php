<?php

namespace App\Services\SupplierOrders;

use App\Models\SupplierOrder;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SupplierOrderDefaults
{
    public const MAILBOX = 'com@food-server.ru';

    public const BODY = "Добрый день!\nПросим принять в работу заказ и подтвердить наличие, цены и срок поставки (возможность отгрузки) указанных товаров:";

    public function forUnit(int $unitId): array
    {
        $sequence = $this->lastSequence($unitId) + 1;
        $date = CarbonImmutable::now('Europe/Moscow')->toDateString();

        return ['mailbox' => self::MAILBOX, 'subject' => $this->subject($date, $sequence), 'body' => self::BODY,
            'subject_date' => $date, 'supplier_sequence' => $sequence, 'auto_subject' => true];
    }

    /** Called inside the draft transaction; the Unit row also exists for its first order. */
    public function reserve(int $unitId): int
    {
        Unit::withoutEagerLoads()->whereKey($unitId)->lockForUpdate()->firstOrFail(['id']);
        // Use a current locking read, not a REPEATABLE READ snapshot taken during
        // normalization. Keep the counter after deletion so numbers are never reused.
        $counter = DB::table('supplier_order_sequences')->where('unit_id', $unitId)->lockForUpdate()->first();
        $sequence = $counter ? (int) $counter->last_sequence + 1 : $this->lastSequence($unitId) + 1;
        DB::table('supplier_order_sequences')->updateOrInsert(['unit_id' => $unitId], ['last_sequence' => $sequence]);

        return $sequence;
    }

    public function subject(string $date, int $sequence): string
    {
        return 'ПИЩЕПРОМ-СЕРВЕР: заказ от '.CarbonImmutable::parse($date, 'Europe/Moscow')->format('d.m.Y')
            .' '.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function lastSequence(int $unitId): int
    {
        return max((int) DB::table('supplier_order_sequences')->where('unit_id', $unitId)->value('last_sequence'),
            (int) SupplierOrder::where('unit_id', $unitId)->max('supplier_sequence'));
    }
}
