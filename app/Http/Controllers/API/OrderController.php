<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Building;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Orders\OrderWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderWriter $writer
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $sortBy = in_array($request->input('sort_by'), [
            'number',
            'submitted_at',
            'total_amount',
            'total_weight',
            'status',
            'entity',
            'items_count',
            'created_at',
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
                ->get(['id', 'code', 'name', 'color', 'is_closed']),
            'entities' => Entity::query()
                ->withoutEagerLoads()
                ->orderBy('name')
                ->get(['id', 'name', 'INN']),
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
                ])
                ->values(),
            'goods' => Good::query()
                ->when($goodSearch !== '', fn (Builder $query) => $query->search($goodSearch))
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'denominator']),
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
        $order->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Order $order = null): array
    {
        return $request->validate([
            'number' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('orders', 'number')->ignore($order?->id),
            ],
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'order_status_id' => ['required', 'integer', 'exists:order_statuses,id'],
            'preferred_delivery_time' => ['nullable', 'string', 'max:255'],
            'internal_comment' => ['nullable', 'string', 'max:10000'],
            'currency_code' => ['required', 'string', 'min:3', 'max:8'],
            'submitted_at' => ['nullable', 'date'],
            'building_ids' => ['nullable', 'array'],
            'building_ids.*' => ['integer', 'distinct', 'exists:buildings,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.good_id' => ['required', 'integer', 'distinct', 'exists:goods,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
    }
}
