<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Builder;

class MobileProductSummary
{
    public function __construct(private readonly OrderFulfillmentService $fulfillment) {}

    public function forDate(string $date): array
    {
        $orders = Order::query()
            ->where('delivery_date', $date)
            ->whereNull('closed_at')
            ->whereNull('shipped_sale_id')
            ->whereNull('shipped_at')
            ->whereHas('status', fn (Builder $status) => $status
                ->where('code', OrderStatus::OPEN)->where('is_closed', false))
            ->with([
                'status', 'contactTelephone:id,number', 'items.measure:id,name',
                ...$this->fulfillment->deliveryRelations(),
            ]);

        $products = [];
        $counts = ['awaiting_orders_count' => 0, 'ready_orders_count' => 0];

        // Aggregate every matching order, independently of the paginated order list.
        foreach ($orders->lazyById(200) as $order) {
            $state = $this->fulfillment->isPrepared($order) ? 'ready' : 'awaiting';
            $counts[$state.'_orders_count']++;
            $seen = [];

            foreach ($order->items as $item) {
                $measurement = $item->measurement();
                $key = $this->key($item, $measurement);
                $products[$key] ??= [
                    'key' => $key,
                    'good_id' => $item->good_id,
                    'name' => $item->good_name,
                    'measure_id' => $measurement['measure_id'] ?? null,
                    'measure_name' => $measurement['unit_label'] ?? null,
                    'kilograms_per_unit' => $measurement['kilograms_per_unit'] ?? null,
                    'awaiting_quantity' => 0.0,
                    'ready_quantity' => 0.0,
                    'awaiting_orders_count' => 0,
                    'ready_orders_count' => 0,
                ];
                $products[$key][$state.'_quantity'] = round(
                    $products[$key][$state.'_quantity'] + $item->quantity, 3,
                );
                if (! isset($seen[$key])) {
                    $products[$key][$state.'_orders_count']++;
                    $seen[$key] = true;
                }
            }
        }

        return [
            'data' => collect($products)->sortBy([
                ['name', 'asc'], ['measure_name', 'asc'], ['key', 'asc'],
            ])->values()->all(),
            'meta' => ['delivery_date' => $date, ...$counts],
        ];
    }

    private function key(OrderItem $item, array $measurement): string
    {
        // Product cards and measure names can change after a line was ordered.
        // Deleted products have no shared identity, even when their names match.
        return hash('sha256', json_encode([
            $item->good_id ? ['good', (int) $item->good_id] : ['item', (int) $item->id],
            isset($measurement['measure_id']) ? (int) $measurement['measure_id'] : null,
            $measurement['unit_label'] ?? null,
            isset($measurement['kilograms_per_unit']) ? (float) $measurement['kilograms_per_unit'] : null,
        ], JSON_THROW_ON_ERROR));
    }
}
