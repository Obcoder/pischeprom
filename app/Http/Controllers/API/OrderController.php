<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Building;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Orders\OrderDataValidator;
use App\Services\Orders\OrderDeliveryDateService;
use App\Services\Orders\OrderDeliveryFilter;
use App\Services\Orders\OrderWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderWriter $writer,
        private readonly OrderDeliveryDateService $delivery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $deliveryFilters = $request->validate(OrderDeliveryFilter::rules($request));
        $perPage = min(max($request->integer('per_page', 100), 1), 100);
        $sortBy = in_array($request->input('sort_by'), [
            'number',
            'submitted_at',
            'total_amount',
            'total_weight',
            'status',
            'entity',
            'items_count',
            'created_at',
            'delivery_date',
        ], true)
            ? $request->input('sort_by')
            : 'submitted_at';
        $direction = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = Order::query()
            ->with($this->writer->relations())
            ->withCount('items')
            ->search($request->input('search'))
            ->when($request->filled('status_id'), fn (Builder $builder) => $builder->where('order_status_id', $request->integer('status_id')))
            ->when($request->filled('status'), function (Builder $builder) use ($request): void {
                $codes = collect(explode(',', (string) $request->input('status')))
                    ->map(fn (string $code) => trim($code))
                    ->filter()
                    ->values();

                $builder->whereHas('status', fn (Builder $status) => $status->whereIn('code', $codes));
            })
            ->when($request->filled('entity_id'), fn (Builder $builder) => $builder->where('entity_id', $request->integer('entity_id')))
            ->when($request->filled('building_id'), fn (Builder $builder) => $builder->whereHas(
                'buildings',
                fn (Builder $building) => $building->whereKey($request->integer('building_id'))
            ))
            ->when($request->filled('good_id'), fn (Builder $builder) => $builder->whereHas(
                'items',
                fn (Builder $item) => $item->where('good_id', $request->integer('good_id'))
            ))
            ->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('submitted_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('submitted_at', '<=', $request->input('date_to')))
            ->when($request->filled('total_from'), fn (Builder $builder) => $builder->where('total_amount', '>=', $request->input('total_from')))
            ->when($request->filled('total_to'), fn (Builder $builder) => $builder->where('total_amount', '<=', $request->input('total_to')));
        OrderDeliveryFilter::apply($query, $deliveryFilters);

        match ($sortBy) {
            'status' => $query->orderBy(
                DB::table('order_statuses')
                    ->select('sort_order')
                    ->whereColumn('order_statuses.id', 'orders.order_status_id')
                    ->limit(1),
                $direction
            ),
            'entity' => $query->orderBy(
                DB::table('entities')
                    ->select('name')
                    ->whereColumn('entities.id', 'orders.entity_id')
                    ->limit(1),
                $direction
            ),
            default => $query->orderBy($sortBy, $direction),
        };

        $orders = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection($orders->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $goodSearch = trim((string) $request->input('good_search'));

        return response()->json([
            'statuses' => OrderStatus::query()
                ->ordered()
                ->get(['id', 'code', 'name', 'color', 'sort_order', 'is_closed']),
            'entities' => Entity::query()
                ->withoutEagerLoads()
                ->with([
                    'classification:id,name',
                    'telephones:id,number',
                    'emails:id,address',
                    'buildings' => fn ($query) => $query->withoutEagerLoads()->select('buildings.id'),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'full_name', 'INN', 'KPP', 'OGRN', 'legal_address', 'entity_classification_id'])
                ->map(fn (Entity $entity) => [
                    ...$entity->only(['id', 'name', 'full_name', 'INN', 'KPP', 'OGRN', 'legal_address']),
                    'classification' => $entity->classification->exists ? $entity->classification->only(['id', 'name']) : null,
                    'telephones' => $entity->telephones->map->only(['id', 'number'])->values(),
                    'emails' => $entity->emails->map->only(['id', 'address'])->values(),
                    'building_ids' => $entity->buildings->pluck('id')->values(),
                ]),
            'buildings' => Building::query()
                ->with(['city:id,name', 'buildingType:id,name'])
                ->orderBy('address')
                ->get()
                ->map(fn (Building $building) => [
                    'id' => $building->id,
                    'address' => $building->address,
                    'postcode' => $building->postcode,
                    'city' => $building->city?->name,
                    'building_type' => $building->buildingType?->name,
                    'apartments' => $building->apartments,
                ])
                ->values(),
            'goods' => Good::query()
                ->with(['priceTypeValues' => fn ($query) => $query
                    ->with(['currency:id,code', 'priceType.currency:id,code'])
                    ->whereHas('priceType', fn (Builder $type) => $type->where('is_active', true))
                    ->whereNotNull('price_gross')
                    ->where('price_gross', '>=', 0)
                    ->where(fn (Builder $date) => $date->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
                    ->where(fn (Builder $date) => $date->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))])
                ->when($goodSearch !== '', fn (Builder $query) => $query->search($goodSearch))
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'denominator', 'measure_id', 'unit_weight_kg'])
                ->map(fn (Good $good) => [
                    ...$good->only(['id', 'name', 'slug', 'denominator', 'measure_id', 'unit_weight_kg']),
                    'measurement' => $good->measurement(),
                    'prices' => $good->priceTypeValues->sortBy(fn (GoodPriceTypeValue $price) => $price->priceType->sort_order)
                        ->map(fn (GoodPriceTypeValue $price) => [
                            'id' => $price->id,
                            'price_gross' => $price->price_gross,
                            'unit_price' => $price->price_gross,
                            'currency_code' => strtoupper($price->currency?->code ?: $price->priceType?->currency?->code ?: 'RUB'),
                            'price_type' => $price->priceType?->only(['id', 'name', 'code']),
                            'measure_id' => $good->measure_id,
                            'unit_label' => $good->measurement()['unit_label'],
                            'vat_rate' => $price->vat_rate,
                            'valid_from' => $price->valid_from?->toDateString(),
                            'valid_to' => $price->valid_to?->toDateString(),
                        ])->values(),
                ]),
            'currency_codes' => Currency::query()
                ->whereNotNull('code')
                ->orderBy('code')
                ->pluck('code')
                ->push('RUB')
                ->filter()
                ->map(fn ($code) => strtoupper((string) $code))
                ->unique()
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by_user_id'] = $request->user()?->id;
        $order = $this->writer->save(null, $data);

        return response()->json([
            'data' => (new OrderResource($order))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load($this->writer->relations())->loadCount('items');

        return response()->json([
            'data' => (new OrderResource($order))->resolve($request),
        ]);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $order = $this->writer->save($order, $this->validated($request, $order));

        return response()->json([
            'data' => (new OrderResource($order))->resolve($request),
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->writer->delete($order);

        return response()->json(null, 204);
    }

    public function deliveryDate(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(OrderDeliveryDateService::updateRules());
        $order = $this->delivery->update($order, $data['delivery_date'], $data['version']);
        $order->load($this->writer->relations())->loadCount('items');

        return response()->json(['data' => (new OrderResource($order))->resolve($request)]);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'order_status_id' => ['required', 'integer', 'exists:order_statuses,id'],
            'expected_order_status_id' => ['required', 'integer'],
        ]);
        $order = $this->writer->updateStatus($order, (int) $data['order_status_id'], (int) $data['expected_order_status_id']);

        return response()->json(['data' => (new OrderResource($order))->resolve($request)]);
    }

    private function validated(Request $request, ?Order $order = null): array
    {
        return app(OrderDataValidator::class)->validated($request, $order);
    }
}
