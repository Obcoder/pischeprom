<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderDeliveryDateService
{
    public function __construct(private readonly OrderFulfillmentService $fulfillment) {}

    public static function dateRules(): array
    {
        return ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'];
    }

    public static function updateRules(): array
    {
        return [
            'version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'delivery_date' => ['present', ...self::dateRules()],
        ];
    }

    public function version(Order $order): string
    {
        // A planning version needs persisted order content, never warehouse balances,
        // staff records or sale details. The admin list already eager-loads these relations.
        $order->loadMissing([
            'status', 'contactTelephone:id,number', 'items', ...$this->fulfillment->deliveryRelations(),
        ]);

        return $this->fulfillment->version($order);
    }

    public function assertVersion(Order $order, string $version): void
    {
        abort_unless(hash_equals($this->version($order), $version), 409, 'Заказ изменился. Обновите данные перед изменением даты доставки.');
    }

    public function update(Order $order, ?string $date, string $version): Order
    {
        return DB::transaction(function () use ($order, $date, $version): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('items', $locked->items()->lockForUpdate()->get());
            $this->assertVersion($locked, $version);

            // Delivery planning remains editable after shipment. Never rewrite sale,
            // stock movements, or the preparation fingerprint for a date-only change.
            $locked->fill(['delivery_date' => $date])->save();

            return $locked->fresh();
        }, 3);
    }
}
