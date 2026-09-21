<?php

namespace App\Services\Orders;

use App\Models\Building;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobileOrderPresenter
{
    public function __construct(private readonly OrderFulfillmentService $fulfillment) {}

    public function one(Order $order): array
    {
        return $this->many(collect([$order]))[0];
    }

    /** Load dictionaries and balances once for a page, including every measure bucket. */
    public function many(Collection $orders): array
    {
        $orders->each(fn (Order $order) => $order->loadMissing($this->fulfillment->relations()));
        $warehouse = $this->fulfillment->warehouse();
        $measures = Measure::query()->orderBy('name')->get(['id', 'name']);
        $goodIds = $orders->flatMap(fn (Order $order) => $order->items->pluck('good_id'))->filter()->unique();
        $balances = $warehouse ? DB::table('good_stock_movements')
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('good_id', $goodIds)
            ->selectRaw('good_id, measure_id, SUM(quantity_delta) as quantity')
            ->groupBy('good_id', 'measure_id')
            ->get()->mapWithKeys(fn ($row) => [$row->good_id.':'.$row->measure_id => (float) $row->quantity])->all() : [];

        return $orders->map(function (Order $order) use ($warehouse, $measures, $balances): array {
            $shipped = $order->shipped_sale_id !== null;
            $prepared = $this->fulfillment->isPrepared($order);
            $problems = $shipped ? [] : $this->fulfillment->problems($order, $warehouse);
            if (! $shipped && ($order->preparation_invalidated_at !== null
                || ($order->prepared_at !== null && ! $prepared))) {
                $problems[] = ['code' => 'order_changed', 'message' => 'Заказ изменён после сборки. Проверьте все позиции и подтвердите сборку заново.'];
            }
            if (! $shipped) {
                $required = $order->items->filter(fn (OrderItem $item) => $item->good_id && $item->measure_id)
                    ->groupBy(fn (OrderItem $item) => $item->good_id.':'.$item->measure_id);
                foreach ($required as $bucket => $items) {
                    if (($balances[$bucket] ?? 0) + 0.000000001 < $items->sum('quantity')) {
                        $problems[] = ['code' => 'insufficient_stock', 'message' => 'Недостаточный остаток: '.$items->first()->good_name.'.'];
                    }
                }
            }

            $sourceWarehouse = $order->fulfillmentWarehouse ?? $warehouse;
            $allowsNegativeStock = $this->fulfillment->allowsNegativeStock();
            $blockingProblems = array_filter($problems, fn (array $problem) => ! $allowsNegativeStock || $problem['code'] !== 'insufficient_stock');

            return [
                'id' => $order->id,
                'number' => $order->number,
                'entity' => $order->entity?->only(['id', 'name']),
                'delivery_addresses' => $this->deliveryAddresses($order),
                'contact_telephone' => $order->contactTelephone ? [
                    'id' => $order->contactTelephone->id,
                    'number' => $order->contactTelephone->number,
                    'dial_number' => $this->dialNumber($order->contactTelephone->number),
                ] : null,
                'submitted_at' => $order->submitted_at?->toISOString(),
                'total_amount' => $order->total_amount,
                'currency_code' => $order->currency_code,
                'items_count' => $order->items->count(),
                'warehouse' => $sourceWarehouse?->only(['id', 'name', 'code']),
                'responsible' => $order->preparedBy?->only(['id', 'name']),
                'workflow_status' => $shipped ? 'shipped' : ($prepared ? 'ready' : 'awaiting'),
                'version' => $this->fulfillment->version($order),
                'warnings' => $problems,
                'allow_negative_stock' => $allowsNegativeStock,
                'can_prepare' => ! $shipped && $this->fulfillment->problems($order, $warehouse, false) === [],
                'can_ship' => ! $shipped && $prepared && $blockingProblems === [],
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'good_id' => $item->good_id,
                    'name' => $item->good_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price_gross,
                    'total' => $item->line_total,
                    'measure_id' => $item->measure_id,
                    'measure_name' => $item->measure?->name,
                    'available_quantity' => $item->measure_id ? ($balances[$item->good_id.':'.$item->measure_id] ?? 0.0) : null,
                    'measure_options' => $measures->map(fn (Measure $measure) => [
                        'id' => $measure->id,
                        'name' => $measure->name,
                        'available_quantity' => $balances[$item->good_id.':'.$measure->id] ?? 0.0,
                    ])->values()->all(),
                ])->values()->all(),
                'sale' => $order->shippedSale ? [
                    'id' => $order->shippedSale->id,
                    'date' => $order->shippedSale->date?->toDateString(),
                    'total' => (float) $order->shippedSale->total,
                ] : null,
                'prepared_at' => $order->prepared_at?->toISOString(),
                'shipped_at' => $order->shipped_at?->toISOString(),
                'shipped_by' => $order->shippedBy?->only(['id', 'name']),
            ];
        })->values()->all();
    }

    public function deliveryRelations(): array
    {
        return $this->fulfillment->deliveryRelations();
    }

    public function deliveryAddresses(Order $order): array
    {
        $order->loadMissing($this->deliveryRelations());

        return $order->buildings
            ->filter(fn (Building $building) => in_array(trim((string) $building->pivot?->role), ['', 'delivery'], true)
                && trim((string) $building->address) !== '')
            ->map(fn (Building $building) => $this->deliveryAddress($building))->values()->all();
    }

    private function deliveryAddress(Building $building): array
    {
        $address = trim((string) $building->address);
        $city = trim((string) $building->city?->name);
        $region = trim((string) $building->city?->region?->name);
        $fullAddress = collect([$region, $city, $address])->filter()->unique()->implode(', ');

        return [
            'id' => $building->id,
            'address' => $address,
            'city' => $city !== '' ? $city : null,
            'full_address' => $fullAddress,
            'yandex_maps_url' => 'https://yandex.ru/maps/?text='.rawurlencode($fullAddress),
        ];
    }

    private function dialNumber(string $number): ?string
    {
        // A dial action must not interpret stored text as a URI, extension or USSD code.
        if (! preg_match('/^\+?[0-9\s().-]+$/u', trim($number))) {
            return null;
        }

        $russian = PhoneNumber::russian($number);
        if ($russian !== null) {
            return $russian;
        }
        $international = preg_replace('/[\s().-]+/u', '', trim($number));

        return preg_match('/^\+[1-9][0-9]{6,14}$/', $international) ? $international : null;
    }
}
