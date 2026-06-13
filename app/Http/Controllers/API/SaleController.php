<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->boolean('server')) {
            return $this->legacyIndex($request);
        }

        $perPage = min(max((int) $request->integer('itemsPerPage', 200), 1), 200);
        $page = max((int) $request->integer('page', 1), 1);
        $sortBy = $request->string('sortBy')->toString() ?: 'date';
        $sortDesc = filter_var($request->get('sortDesc', true), FILTER_VALIDATE_BOOLEAN);

        $query = Sale::query()
            ->with([
                'entity.units:id,name',
                'entity.buildings.city:id,name',
                'entity.cities:id,name',
                'goods.vatRate:id,title,rate',
                'goods' => function ($goodsQuery): void {
                    $goodsQuery->select('goods.id', 'goods.name', 'goods.slug', 'goods.denominator', 'goods.vat_rate_id');
                },
            ]);

        $this->applyFilters($query, $request);
        $this->applySort($query, $sortBy, $sortDesc);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $sales = collect($paginator->items());
        $this->attachPreviousSales($sales);

        return response()->json([
            'data' => $sales->map(fn (Sale $sale) => $this->serializeSale($sale))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'months' => $this->saleMonths(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'goods' => ['nullable', 'array'],
            'goods.*.good_id' => ['required_with:goods', 'integer', 'exists:goods,id'],
            'goods.*.measure_id' => ['required_with:goods', 'integer', 'exists:measures,id'],
            'goods.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'goods.*.price' => ['nullable', 'numeric', 'min:0'],
            'goods.*.total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = collect($validated['goods'] ?? [])
            ->map(fn (array $line) => $this->normalizeSaleLine($line))
            ->values();

        $computedTotal = $lines->sum('total');
        $saleTotal = array_key_exists('total', $validated) && $validated['total'] !== null
            ? (float) $validated['total']
            : (float) $computedTotal;

        $sale = DB::transaction(function () use ($validated, $lines, $saleTotal) {
            $sale = Sale::create([
                'date' => Carbon::parse($validated['date'])->toDateString(),
                'entity_id' => $validated['entity_id'],
                'total' => $saleTotal,
            ]);

            foreach ($lines as $line) {
                $sale->goods()->attach($line['good_id'], [
                    'quantity' => $line['quantity'],
                    'measure_id' => $line['measure_id'],
                    'price' => $line['price'],
                ]);
            }

            return $sale;
        });

        $sale->load([
            'entity.units:id,name',
            'entity.buildings.city:id,name',
            'entity.cities:id,name',
            'goods.vatRate:id,title,rate',
        ]);

        $this->attachPreviousSales(collect([$sale]));

        return response()->json([
            'data' => $this->serializeSale($sale),
        ], 201);
    }

    public function show(string $id)
    {
        $sale = Sale::with([
            'entity.units:id,name',
            'entity.buildings.city:id,name',
            'entity.cities:id,name',
            'goods.vatRate:id,title,rate',
        ])->findOrFail($id);

        $this->attachPreviousSales(collect([$sale]));

        return response()->json($this->serializeSale($sale));
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }

    private function legacyIndex(Request $request)
    {
        $productId = $request->query('product_id');

        $query = Sale::query()->orderBy('date', 'desc');

        if ($productId) {
            $query->byProduct((int) $productId);
        }

        return $query
            ->with(['goods.products', 'entity'])
            ->get();
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('month')) {
            [$year, $month] = explode('-', (string) $request->input('month'));
            $query->whereYear('date', (int) $year)->whereMonth('date', (int) $month);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', Carbon::parse($request->input('date_from'))->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', Carbon::parse($request->input('date_to'))->toDateString());
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', (int) $request->input('entity_id'));
        }
    }

    private function applySort($query, string $sortBy, bool $sortDesc): void
    {
        $direction = $sortDesc ? 'desc' : 'asc';

        match ($sortBy) {
            'total' => $query->orderBy('total', $direction),
            'entity.name' => $query
                ->leftJoin('entities', 'entities.id', '=', 'sales.entity_id')
                ->orderBy('entities.name', $direction)
                ->select('sales.*'),
            default => $query->orderBy('date', $direction)->orderBy('id', $direction),
        };
    }

    private function normalizeSaleLine(array $line): array
    {
        $quantity = $this->nullableFloat($line['quantity'] ?? null);
        $price = $this->nullableFloat($line['price'] ?? null);
        $total = $this->nullableFloat($line['total'] ?? null);

        if ($total !== null && $quantity !== null && $quantity > 0 && $price === null) {
            $price = $total / $quantity;
        }

        if ($total !== null && $price !== null && $price > 0 && $quantity === null) {
            $quantity = $total / $price;
        }

        if ($quantity !== null && $price !== null && $total === null) {
            $total = $quantity * $price;
        }

        if ($quantity === null || $price === null || $total === null) {
            throw ValidationException::withMessages([
                'goods' => 'Для каждой позиции нужно указать любые два значения из: количество, цена, сумма.',
            ]);
        }

        return [
            'good_id' => (int) $line['good_id'],
            'measure_id' => (int) $line['measure_id'],
            'quantity' => round($quantity, 6),
            'price' => round($price, 6),
            'total' => round($total, 2),
        ];
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function attachPreviousSales($sales): void
    {
        $sales->each(function (Sale $sale): void {
            $previous = Sale::query()
                ->where('entity_id', $sale->entity_id)
                ->where(function ($query) use ($sale): void {
                    $query
                        ->whereDate('date', '<', $sale->date)
                        ->orWhere(function ($sameDateQuery) use ($sale): void {
                            $sameDateQuery
                                ->whereDate('date', '=', $sale->date)
                                ->where('id', '<', $sale->id);
                        });
                })
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first(['id', 'date', 'total']);

            $sale->setAttribute('previous_sale', $previous);
        });
    }

    private function saleMonths(): array
    {
        return Sale::query()
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as value, DATE_FORMAT(date, '%m.%Y') as label, COUNT(*) as count, SUM(total) as total")
            ->groupBy('value', 'label')
            ->orderByDesc('value')
            ->limit(36)
            ->get()
            ->map(fn ($row) => [
                'value' => $row->value,
                'label' => $row->label,
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ])
            ->values()
            ->all();
    }

    private function serializeSale(Sale $sale): array
    {
        $previous = $sale->getAttribute('previous_sale');
        $saleDate = Carbon::parse($sale->date);

        return [
            'id' => $sale->id,
            'date' => $saleDate->toDateString(),
            'month' => $saleDate->format('Y-m'),
            'entity_id' => $sale->entity_id,
            'total' => (float) $sale->total,
            'entity' => $sale->entity ? [
                'id' => $sale->entity->id,
                'name' => $sale->entity->name,
                'full_name' => $sale->entity->full_name,
                'units' => $sale->entity->relationLoaded('units')
                    ? $sale->entity->units->map(fn ($unit) => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                    ])->values()
                    : [],
                'buildings' => $sale->entity->relationLoaded('buildings')
                    ? $sale->entity->buildings->map(fn ($building) => [
                        'id' => $building->id,
                        'address' => $building->address,
                        'city' => $building->city ? [
                            'id' => $building->city->id,
                            'name' => $building->city->name,
                        ] : null,
                    ])->values()
                    : [],
            ] : null,
            'goods' => $sale->relationLoaded('goods')
                ? $sale->goods->map(fn (Good $good) => [
                    'id' => $good->id,
                    'name' => $good->name,
                    'denominator' => $good->denominator,
                    'vat_rate' => $good->vatRate ? [
                        'id' => $good->vatRate->id,
                        'title' => $good->vatRate->title,
                        'rate' => $good->vatRate->rate,
                    ] : null,
                    'pivot' => [
                        'quantity' => (float) $good->pivot->quantity,
                        'measure_id' => (int) $good->pivot->measure_id,
                        'price' => (float) $good->pivot->price,
                        'total' => (float) $good->pivot->total,
                    ],
                ])->values()
                : [],
            'previous_sale' => $previous ? [
                'id' => $previous->id,
                'date' => Carbon::parse($previous->date)->toDateString(),
                'total' => (float) $previous->total,
                'days' => Carbon::parse($previous->date)->diffInDays($saleDate),
            ] : null,
        ];
    }
}
