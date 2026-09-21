<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Goods\GoodSaleStockSynchronizer;
use App\Services\Goods\SaleStockRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFulfillmentService
{
    public function __construct(
        private readonly GoodSaleStockSynchronizer $stock,
        private readonly SaleStockRequestService $requests,
    ) {}

    public function relations(): array
    {
        return [
            'entity' => fn ($query) => $query->withoutEagerLoads()->select(['id', 'name']),
            'status', 'items.good:id,name', 'items.measure:id,name',
            'preparedBy:id,name', 'shippedBy:id,name', 'fulfillmentWarehouse',
            'shippedSale' => fn ($query) => $query->withoutEagerLoads(),
        ];
    }

    /** Hash persisted business fields, never the client's prices or totals. */
    public function fingerprint(Order $order): string
    {
        return hash('sha256', json_encode([
            'order' => $order->only([
                'id', 'number', 'entity_id', 'order_status_id', 'contact_telephone_id',
                'preferred_delivery_time', 'internal_comment', 'total_amount', 'total_weight',
                'currency_code', 'submitted_at', 'closed_at', 'fulfillment_warehouse_id',
            ]),
            'status' => $order->status?->only(['code', 'is_closed']),
            'items' => $order->items->map(fn (OrderItem $item) => $item->only([
                'id', 'good_id', 'good_name', 'quantity', 'denominator', 'line_weight',
                'price_gross', 'currency_code', 'line_total', 'measure_id',
            ]))->values()->all(),
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function version(Order $order): string
    {
        return hash('sha256', json_encode([
            $this->fingerprint($order),
            $order->only([
                'prepared_at', 'prepared_by_user_id', 'prepared_fingerprint', 'preparation_invalidated_at',
                'shipped_sale_id', 'shipped_by_user_id', 'shipped_at',
            ]),
        ], JSON_THROW_ON_ERROR));
    }

    public function isPrepared(Order $order): bool
    {
        return $order->prepared_at !== null
            && $order->prepared_fingerprint !== null
            && hash_equals($order->prepared_fingerprint, $this->fingerprint($order));
    }

    /** Data problems are also shown in the list before an employee starts assembly. */
    public function problems(Order $order, ?Warehouse $warehouse, bool $requireMeasures = true): array
    {
        $problems = [];
        if ($order->status?->code !== OrderStatus::OPEN || $order->status?->is_closed || $order->closed_at !== null) {
            $problems[] = ['code' => 'order_not_open', 'message' => 'Заказ закрыт или отложен. Сначала откройте его в основном приложении.'];
        }
        if (! $order->entity) {
            $problems[] = ['code' => 'missing_customer', 'message' => 'У заказа не указан покупатель.'];
        }
        if (! $warehouse || ($order->fulfillment_warehouse_id && $order->fulfillment_warehouse_id !== $warehouse->id)) {
            $problems[] = ['code' => 'missing_warehouse', 'message' => 'Активный системный склад товаров недоступен.'];
        }
        if ($order->currency_code !== 'RUB' || $order->items->contains(fn (OrderItem $item) => $item->currency_code !== 'RUB')) {
            $problems[] = ['code' => 'unsupported_currency', 'message' => 'Отгрузка поддерживает только заказы в рублях. Исправьте валюту и цены в основном приложении.'];
        }
        if ($order->items->isEmpty()) {
            $problems[] = ['code' => 'empty_order', 'message' => 'В заказе нет товаров.'];
        }

        $total = 0.0;
        foreach ($order->items as $item) {
            if (! $item->good) {
                $problems[] = ['code' => 'missing_good', 'message' => "Товар «{$item->good_name}» удалён из справочника."];
            }
            if ($requireMeasures && ! $item->measure) {
                $problems[] = ['code' => 'missing_measure', 'message' => "Выберите единицу измерения: {$item->good_name}."];
            }
            $quantity = (float) $item->quantity;
            $price = (float) $item->price_gross;
            $lineTotal = round($quantity * $price, 4);
            if (! is_finite($quantity) || $quantity <= 0 || $quantity > 999999999) {
                $problems[] = ['code' => 'invalid_quantity', 'message' => "Проверьте количество: {$item->good_name}."];
            }
            if ($item->price_gross === null || ! is_finite($price) || $price < 0 || $price >= 1e12
                || ! is_finite($lineTotal) || $lineTotal >= 1e12 || $item->line_total === null
                || abs($lineTotal - (float) $item->line_total) > 0.000051) {
                $problems[] = ['code' => 'invalid_price', 'message' => "Проверьте цену и сумму: {$item->good_name}."];
            }
            $total += $lineTotal;
        }
        if (! is_finite($total) || $total < 0 || $total >= 1e12
            || ! is_finite((float) $order->total_amount) || abs($total - (float) $order->total_amount) > 0.000051) {
            $problems[] = ['code' => 'invalid_total', 'message' => 'Сумма заказа не совпадает с суммой его позиций. Пересохраните заказ в основном приложении.'];
        } elseif (abs($this->saleTotal($order) - round($order->total_amount, 2)) > 0.000001) {
            $problems[] = ['code' => 'rounding_mismatch', 'message' => 'После округления каждой позиции до копеек сумма продажи отличается от суммы заказа. Уточните цены в основном приложении перед отгрузкой.'];
        }

        return $problems;
    }

    public function prepare(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor): Order {
            $order = $this->lockedOrder($order->id);
            abort_if($order->shipped_sale_id !== null, 409, 'Заказ уже отгружен.');
            $this->assertVersion($order, $data['version']);
            $warehouse = $this->warehouse();
            $this->assertNoProblems($this->problems($order, $warehouse, false));

            $submitted = collect($data['items'])->keyBy('id');
            if ($submitted->count() !== $order->items->count()
                || $order->items->contains(fn (OrderItem $item) => ! $submitted->has($item->id))) {
                throw ValidationException::withMessages(['items' => 'Подтвердите все позиции заказа. Частичная отгрузка пока не поддерживается.']);
            }
            foreach ($order->items as $item) {
                $line = $submitted->get($item->id);
                if (! is_finite((float) $line['quantity']) || abs((float) $line['quantity'] - (float) $item->quantity) > 0.000000001) {
                    throw ValidationException::withMessages(['items' => 'Количество должно совпадать с заказом. Измените заказ в основном приложении перед сборкой.']);
                }
                $item->update(['measure_id' => (int) $line['measure_id']]);
            }
            $order->forceFill([
                'fulfillment_warehouse_id' => $warehouse->id,
                'prepared_by_user_id' => $actor->id,
                'prepared_at' => now(),
                'preparation_invalidated_at' => null,
            ])->save();
            $order = $order->fresh($this->relations());
            $order->forceFill(['prepared_fingerprint' => $this->fingerprint($order)])->save();

            return $order->fresh($this->relations());
        }, 3);
    }

    public function ship(Order $order, array $data, User $actor): Order
    {
        // Claim the UUID first: successful retries return the original sale even though
        // the order version changed after posting. Both claim and posting roll back on failure.
        $sale = $this->requests->run($data['request_id'], 'mobile.order.ship', [
            'actor_id' => $actor->id,
            'order_id' => $order->id,
            'version' => $data['version'],
        ], function () use ($order, $data, $actor): Sale {
            $order = $this->lockedOrder($order->id);
            abort_if($order->shipped_sale_id !== null, 409, 'Заказ уже отгружен. Обновите данные.');
            $this->assertVersion($order, $data['version']);
            abort_unless($this->isPrepared($order), 409, 'Заказ изменён или ещё не собран. Проверьте и подтвердите сборку.');
            $this->assertNoProblems($this->problems($order, $this->warehouse()));

            $sale = Sale::query()->create([
                'date' => now()->toDateString(),
                'entity_id' => $order->entity_id,
                'total' => $this->saleTotal($order),
            ]);
            foreach ($order->items as $item) {
                $sale->goods()->attach($item->good_id, [
                    'quantity' => $item->quantity,
                    'price' => $item->price_gross,
                    'measure_id' => $item->measure_id,
                ]);
            }
            // Existing posting code locks goods, checks balances and calculates stock cost.
            $this->stock->sync($sale);
            $order->forceFill([
                'shipped_sale_id' => $sale->id,
                'shipped_by_user_id' => $actor->id,
                'shipped_at' => now(),
                'closed_at' => now(),
                'order_status_id' => OrderStatus::query()->where('code', OrderStatus::CLOSED)->sole()->id,
            ])->save();

            return $sale;
        });

        $order = $order->fresh($this->relations());
        abort_unless($order && (int) $order->shipped_sale_id === (int) $sale->id, 409, 'Результат отгрузки не соответствует заказу.');

        return $order;
    }

    public function warehouse(): ?Warehouse
    {
        return Warehouse::query()->where('code', Warehouse::GOODS_CODE)->where('is_active', true)->first();
    }

    private function saleTotal(Order $order): float
    {
        // Match SaleController: round each quantity × price to kopecks before summing.
        // Orders retain four decimals; problems() prevents silently changing their payable total.
        return round($order->items->sum(fn (OrderItem $item) => round($item->quantity * $item->price_gross, 2)), 2);
    }

    private function lockedOrder(int $id): Order
    {
        $order = Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        $order->load($this->relations());
        $order->setRelation('items', $order->items()->with(['good:id,name', 'measure:id,name'])->lockForUpdate()->get());

        return $order;
    }

    private function assertVersion(Order $order, string $version): void
    {
        abort_unless(hash_equals($this->version($order), $version), 409, 'Заказ изменился. Обновите данные и проверьте заказ заново.');
    }

    private function assertNoProblems(array $problems): void
    {
        if ($problems !== []) {
            throw ValidationException::withMessages(['order' => array_column($problems, 'message')]);
        }
    }
}
