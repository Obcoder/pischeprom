<?php

namespace App\Http\Controllers\API;

use App\Domain\Banking\Events\ReceivablePaymentStatusChanged;
use App\Domain\Banking\Services\DecimalMoney;
use App\Domain\Banking\Services\PaymentAllocationService;
use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Order;
use App\Models\Sale;
use App\Observers\CommerceDataObserver;
use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\GoodSaleStockSynchronizer;
use App\Services\Goods\GoodStockMutationService;
use App\Services\Goods\SaleStockRequestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                    $goodsQuery->select('goods.id', 'goods.name', 'goods.slug', 'goods.denominator', 'goods.measure_id', 'goods.unit_weight_kg', 'goods.vat_rate_id');
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
                'total_amount' => $this->salesTotal($request),
                'months' => $this->saleMonths($request),
                'goods_summary' => $request->boolean('goods_summary') ? $this->goodsSummary($request) : [],
            ],
        ]);
    }

    public function store(Request $request, GoodSaleStockSynchronizer $stock, SaleStockRequestService $requests)
    {
        $validated = $request->validate([
            'request_id' => ['nullable', 'uuid'],
            'date' => ['required', 'date'],
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'payment_reference' => ['nullable', 'string', 'max:128'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'goods' => ['nullable', 'array'],
            'goods.*.good_id' => ['required_with:goods', 'integer', 'exists:goods,id'],
            'goods.*.measure_id' => ['nullable', 'integer', 'exists:measures,id'],
            'goods.*.quantity' => ['nullable', 'numeric', 'min:0.000001'],
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

        if (! is_finite($saleTotal) || $saleTotal < 0 || $saleTotal >= 1e18) {
            throw ValidationException::withMessages([
                'total' => 'Укажите допустимую неотрицательную сумму продажи.',
            ]);
        }

        $payload = [
            'actor_id' => $request->user()?->getAuthIdentifier(),
            'date' => Carbon::parse($validated['date'])->toDateString(),
            'entity_id' => (int) $validated['entity_id'],
            'total' => round($saleTotal, 2),
            'goods' => $lines->all(),
        ];
        if (isset($validated['payment_reference'])) {
            $payload['payment_reference'] = $validated['payment_reference'];
        }

        $sale = $requests->run($validated['request_id'] ?? null, 'sale.store', $payload, function () use ($payload, $lines, $stock) {
            if (isset($payload['payment_reference'])
                && Sale::query()->where('payment_reference', $payload['payment_reference'])->exists()) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'Это назначение платежа уже используется другой продажей.',
                ]);
            }
            $this->assertCurrentUnits($lines->all());
            $sale = Sale::create([
                'date' => $payload['date'],
                'entity_id' => $payload['entity_id'],
                'payment_reference' => $payload['payment_reference'] ?? null,
                'total' => $payload['total'],
            ]);

            foreach ($lines as $line) {
                $sale->goods()->attach($line['good_id'], [
                    'quantity' => $line['quantity'],
                    'measure_id' => $line['measure_id'],
                    'price' => $line['price'],
                ]);
            }

            $stock->sync($sale);

            return $sale;
        });

        $sale->load([
            'entity.units:id,name',
            'entity.buildings.city:id,name',
            'entity.cities:id,name',
            'goods.vatRate:id,title,rate',
        ]);

        $this->attachPreviousSales(collect([$sale]));

        if ($request->header('X-Inertia')) {
            return back();
        }

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

    public function storeGood(
        Request $request,
        Sale $sale,
        PaymentAllocationService $paymentAllocations,
        GoodSaleStockSynchronizer $stock,
        SaleStockRequestService $requests,
    ) {
        $validated = $request->validate([
            'request_id' => ['nullable', 'uuid'],
            'good_id' => ['required', 'integer', 'exists:goods,id'],
            'measure_id' => ['nullable', 'integer', 'exists:measures,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.000001'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $line = $this->normalizeSaleLine($validated);
        $payload = [
            'actor_id' => $request->user()?->getAuthIdentifier(),
            'sale_id' => $sale->id,
            'good' => $line,
        ];
        $sale = $requests->run($validated['request_id'] ?? null, 'sale.goods.store', $payload, function () use ($sale, $line, $paymentAllocations, $stock): Sale {
            $lockedSale = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(Order::query()->where('shipped_sale_id', $lockedSale->id)
                ->lockForUpdate()->first(['id']) !== null, 409,
                'Продажа создана отгрузкой заказа. Добавлять позиции в неё нельзя.');

            $this->assertCurrentUnits([$line]);

            $lockedSale->goods()->attach($line['good_id'], [
                'quantity' => $line['quantity'],
                'measure_id' => $line['measure_id'],
                'price' => $line['price'],
            ]);

            $stock->sync($lockedSale);

            $total = DecimalMoney::add(
                (string) $lockedSale->total,
                number_format($line['total'], 2, '.', ''),
            );

            if ((float) $total >= 1e18) {
                throw ValidationException::withMessages([
                    'total' => 'Сумма продажи превышает допустимое значение.',
                ]);
            }

            $lockedSale->forceFill(['total' => $total]);
            $statusChange = $paymentAllocations->recalculateSaleLocked($lockedSale);

            if ($statusChange !== null) {
                DB::afterCommit(fn () => ReceivablePaymentStatusChanged::dispatch(
                    $lockedSale,
                    $statusChange['previous'],
                    $statusChange['current'],
                ));
            }

            return $lockedSale;
        });

        $sale->load([
            'entity.units:id,name',
            'entity.buildings.city:id,name',
            'entity.cities:id,name',
            'goods.vatRate:id,title,rate',
        ]);

        $this->attachPreviousSales(collect([$sale]));

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'data' => $this->serializeSale($sale),
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        GoodStockMutationService $mutations,
        GoodSaleStockSynchronizer $stock,
        PaymentAllocationService $paymentAllocations,
    ) {
        $fullEdit = $request->hasAny(['entity_id', 'total', 'payment_reference', 'goods']);
        if ($fullEdit) {
            $this->assertAdministrator($request);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'entity_id' => ['sometimes', 'required', 'integer', 'exists:entities,id'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:128', Rule::unique('sales', 'payment_reference')->ignore($id)],
            'total' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'goods' => ['sometimes', 'array'],
            'goods.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'goods.*.good_id' => ['required', 'integer', 'exists:goods,id'],
            'goods.*.measure_id' => ['nullable', 'integer', 'exists:measures,id'],
            'goods.*.quantity' => ['nullable', 'numeric', 'min:0.000001'],
            'goods.*.price' => ['nullable', 'numeric', 'min:0'],
            'goods.*.total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = DB::transaction(function () use ($id, $validated, $mutations, $stock, $paymentAllocations): Sale {
            $sale = Sale::query()->without('entity')->whereKey($id)
                ->lockForUpdate()->firstOrFail();
            $existing = DB::table('good_sale')->where('sale_id', $sale->id)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $lines = array_key_exists('goods', $validated)
                ? collect($validated['goods'])->map(function (array $line) use ($existing): array {
                    $lineId = isset($line['id']) ? (int) $line['id'] : null;
                    $previous = $lineId !== null ? $existing->get($lineId) : null;
                    if ($lineId !== null && $previous === null) {
                        throw ValidationException::withMessages([
                            'goods' => 'Позиция не принадлежит этой продаже. Обновите данные продажи.',
                        ]);
                    }

                    // Existing documents keep their unit snapshot when editing metadata or prices.
                    $historicalMeasureId = $previous
                        && (int) $previous->measure_id > 0
                        && (int) $previous->good_id === (int) $line['good_id']
                        && (! isset($line['measure_id']) || (int) $previous->measure_id === (int) $line['measure_id'])
                        ? (int) $previous->measure_id
                        : null;

                    return [...$this->normalizeSaleLine($line, $historicalMeasureId, allowUnconfiguredMeasure: true), 'id' => $lineId];
                })->all()
                : null;

            $goodsChanged = $lines !== null && $this->saleLinesChanged($existing, $lines);
            $stockChanged = $lines !== null && $this->saleLinesChanged($existing, $lines, stockOnly: true);
            $entityChanged = isset($validated['entity_id']) && (int) $validated['entity_id'] !== (int) $sale->entity_id;
            $total = $validated['total'] ?? ($lines !== null ? array_sum(array_column($lines, 'total')) : (string) $sale->total);

            if (! is_finite((float) $total) || $total < 0 || $total >= 1e18) {
                throw ValidationException::withMessages([
                    'total' => 'Укажите допустимую неотрицательную сумму продажи.',
                ]);
            }

            $total = isset($validated['total']) || $lines !== null
                ? number_format((float) $total, 2, '.', '')
                : (string) $sale->total;
            $totalChanged = DecimalMoney::compare((string) $sale->total, $total) !== 0;

            if ($goodsChanged || $entityChanged || $totalChanged) {
                abort_if(Order::query()->where('shipped_sale_id', $sale->id)
                    ->lockForUpdate()->first(['id']) !== null, 409,
                    'Продажа создана отгрузкой заказа. Изменение покупателя, суммы и позиций такой продажи недоступно.');
            }

            if ($entityChanged) {
                abort_if($sale->activeBankAllocations()->lockForUpdate()->first(['id']) !== null, 409,
                    'Нельзя изменить покупателя продажи с привязанной оплатой. Сначала отмените распределение оплаты в банковском учёте.');
            }

            $sale->fill(collect($validated)->only(['date', 'entity_id', 'payment_reference'])->all());
            if ($totalChanged) {
                $sale->total = $total;
            }
            $sale->save();
            $saleChanged = $sale->wasChanged();

            if ($goodsChanged) {
                $goodIds = $existing->pluck('good_id')->merge(array_column($lines, 'good_id'))
                    ->merge(GoodStockMovement::query()->where('sale_id', $sale->id)->pluck('good_id'))
                    ->unique()->all();

                $mutations->run($goodIds, function () use ($sale, $lines, $existing, $stockChanged, $stock): void {
                    $this->assertCurrentUnits(array_values(array_filter($lines, function (array $line) use ($existing): bool {
                        $previous = $line['id'] !== null ? $existing->get($line['id']) : null;

                        return $previous === null || $this->saleLinesChanged(collect([$previous])->keyBy('id'), [$line], stockOnly: true);
                    })), allowUnconfiguredMeasure: true);
                    $keptIds = [];
                    foreach ($lines as $line) {
                        $attributes = collect($line)->only(['good_id', 'measure_id', 'quantity', 'price'])->all();
                        if ($line['id'] !== null) {
                            DB::table('good_sale')->where('sale_id', $sale->id)->where('id', $line['id'])
                                ->update([...$attributes, 'updated_at' => now()]);
                            $keptIds[] = $line['id'];
                        } else {
                            $keptIds[] = DB::table('good_sale')->insertGetId([
                                ...$attributes,
                                'sale_id' => $sale->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    DB::table('good_sale')->where('sale_id', $sale->id)
                        ->whereIn('id', $existing->keys()->diff($keptIds)->all())->delete();

                    if ($stockChanged) {
                        $stock->sync($sale);
                    }
                }, allowNegativeStock: true);
            }

            if (! $stockChanged) {
                $this->updateStockDates($sale, $validated['date'], $mutations);
            }

            if ($goodsChanged && ! $stockChanged && ! $saleChanged) {
                app(CommerceDataObserver::class)->publishAfterCommit($sale);
            }

            if ($totalChanged) {
                $statusChange = $paymentAllocations->recalculateSaleLocked($sale);
                if ($statusChange !== null) {
                    DB::afterCommit(fn () => ReceivablePaymentStatusChanged::dispatch(
                        $sale,
                        $statusChange['previous'],
                        $statusChange['current'],
                    ));
                }
            }

            return $sale;
        }, 3);

        $sale->load([
            'entity.units:id,name',
            'entity.buildings.city:id,name',
            'entity.cities:id,name',
            'goods.vatRate:id,title,rate',
        ]);
        $this->attachPreviousSales(collect([$sale]));

        return response()->json([
            'data' => $this->serializeSale($sale),
        ]);
    }

    public function destroy(Request $request, string $id, GoodStockMutationService $mutations)
    {
        $this->assertAdministrator($request);

        DB::transaction(function () use ($id, $mutations): void {
            $sale = Sale::query()->without('entity')->whereKey($id)->lockForUpdate()->firstOrFail();

            abort_if(Order::query()->where('shipped_sale_id', $sale->id)
                ->lockForUpdate()->first(['id']) !== null, 409,
                'Нельзя удалить продажу, созданную отгрузкой заказа.');
            abort_if($sale->bankAllocations()->lockForUpdate()->first(['id']) !== null, 409,
                'Нельзя удалить продажу с историей банковских оплат. Для корректировки измените сумму и позиции продажи.');

            $movements = GoodStockMovement::query()
                ->where('source_type', GoodStockMovement::SOURCE_GOOD_SALE)
                ->where('sale_id', $sale->id);
            $mutations->run($movements->pluck('good_id')->all(), function () use ($sale, $movements): void {
                $movements->orderBy('id')->lockForUpdate()->get()->each->delete();
                $sale->goods()->detach();

                // Keep UUID tombstones so a retry cannot recreate a deleted sale.
                DB::table('sale_stock_requests')->where('sale_id', $sale->id)->update([
                    'action' => 'sale.deleted',
                    'sale_id' => null,
                    'updated_at' => now(),
                ]);
                $sale->delete();
            });
        }, 3);

        return response()->noContent();
    }

    private function assertAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin', 'crm'), 403,
            'Полное редактирование и удаление продаж доступно только администратору.');
    }

    private function saleLinesChanged($existing, array $lines, bool $stockOnly = false): bool
    {
        if ($existing->count() !== count($lines)) {
            return true;
        }

        foreach ($lines as $line) {
            $previous = $line['id'] !== null ? $existing->get($line['id']) : null;
            if (! $previous
                || (int) $previous->good_id !== $line['good_id']
                || (int) $previous->measure_id !== $line['measure_id']
                || round((float) $previous->quantity, 6) !== $line['quantity']
                || (! $stockOnly && round((float) $previous->price, 6) !== $line['price'])) {
                return true;
            }
        }

        return false;
    }

    private function updateStockDates(Sale $sale, string $date, GoodStockMutationService $mutations): void
    {
        $movements = GoodStockMovement::query()
            ->where('source_type', GoodStockMovement::SOURCE_GOOD_SALE)
            ->where('sale_id', $sale->id);

        // Date and price corrections must not post missing historical stock or reprice it.
        $mutations->run($movements->pluck('good_id')->all(), function () use ($movements, $date): void {
            $movements->orderBy('id')->lockForUpdate()->get()
                ->each(fn (GoodStockMovement $movement) => $movement->update(['moved_at' => $date]));
        });
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
        $this->applyContextFilters($query, $request);
    }

    private function applyContextFilters($query, Request $request, bool $includeMonth = true): void
    {
        if ($includeMonth && $request->filled('month')) {
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

    private function normalizeSaleLine(array $line, ?int $historicalMeasureId = null, bool $allowUnconfiguredMeasure = false): array
    {
        $good = Good::query()->findOrFail($line['good_id']);
        $measureId = $historicalMeasureId ?? $this->resolveSaleMeasure($good, isset($line['measure_id']) ? (int) $line['measure_id'] : null, $allowUnconfiguredMeasure);
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

        if (
            ! is_finite($quantity) || $quantity < 0.000001
            || ! is_finite($price) || $price < 0
            || ! is_finite($total) || $total < 0 || $total >= 1e18
            || ! is_finite($quantity * $price)
        ) {
            throw ValidationException::withMessages([
                'goods' => 'Количество должно быть не меньше 0,000001, цена и сумма — допустимыми неотрицательными числами.',
            ]);
        }

        $quantity = round($quantity, 6);
        $price = round($price, 6);
        $canonicalTotal = round($quantity * $price, 2);

        if (! is_finite($canonicalTotal) || $canonicalTotal >= 1e18) {
            throw ValidationException::withMessages([
                'goods' => 'Сумма позиции превышает допустимое значение.',
            ]);
        }

        if (isset($line['total']) && abs($total - $canonicalTotal) > 0.010000001) {
            throw ValidationException::withMessages([
                'goods' => 'Сумма позиции должна совпадать с произведением количества и цены.',
            ]);
        }

        return [
            'good_id' => (int) $line['good_id'],
            'measure_id' => $measureId,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $canonicalTotal,
        ];
    }

    private function resolveSaleMeasure(Good $good, ?int $measureId, bool $allowUnconfiguredMeasure): int
    {
        // Admin corrections may supply a document unit for an unconfigured legacy good.
        // The product card and configured accounting units retain their own rules.
        if ($allowUnconfiguredMeasure && $good->measure_id === null) {
            if ($measureId !== null) {
                return $measureId;
            }

            throw ValidationException::withMessages([
                'goods' => "Выберите единицу измерения товара «{$good->name}» в продаже.",
            ]);
        }

        return app(GoodMeasurement::class)->assertMeasure($good, $measureId, 'goods');
    }

    private function assertCurrentUnits(array $lines, bool $allowUnconfiguredMeasure = false): void
    {
        $goods = Good::query()->whereIn('id', array_column($lines, 'good_id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($lines as $line) {
            $this->resolveSaleMeasure($goods->get($line['good_id']), $line['measure_id'], $allowUnconfiguredMeasure);
        }
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < 0) {
            throw ValidationException::withMessages([
                'goods' => 'Значения позиции должны быть конечными неотрицательными числами.',
            ]);
        }

        return $number;
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

    private function saleMonths(Request $request): array
    {
        $query = Sale::query()
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as value, DATE_FORMAT(date, '%m.%Y') as label, COUNT(*) as count, SUM(total) as total")
            ->groupBy('value', 'label');

        $this->applyContextFilters($query, $request, includeMonth: false);

        return $query
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

    private function salesTotal(Request $request): float
    {
        $query = Sale::query();

        $this->applyContextFilters($query, $request);

        return (float) $query->sum('total');
    }

    private function goodsSummary(Request $request): array
    {
        $query = DB::table('good_sale')
            ->join('sales', 'sales.id', '=', 'good_sale.sale_id')
            ->join('goods', 'goods.id', '=', 'good_sale.good_id')
            ->leftJoin('measures', 'measures.id', '=', 'good_sale.measure_id')
            ->leftJoin('vat_rates', 'vat_rates.id', '=', 'goods.vat_rate_id')
            ->select([
                'goods.id',
                'goods.name',
                'goods.slug',
                'goods.denominator',
                'good_sale.measure_id',
                'measures.name as measure_name',
                'vat_rates.id as vat_rate_id',
                'vat_rates.title as vat_rate_title',
                'vat_rates.rate as vat_rate_rate',
            ])
            ->selectRaw('COUNT(DISTINCT sales.id) as sales_count')
            ->selectRaw('SUM(good_sale.quantity) as quantity')
            ->selectRaw('AVG(good_sale.price) as average_price')
            ->selectRaw('SUM(good_sale.total) as total')
            ->selectRaw('MAX(sales.date) as last_sale_date')
            ->groupBy([
                'goods.id',
                'goods.name',
                'goods.slug',
                'goods.denominator',
                'good_sale.measure_id',
                'measures.name',
                'vat_rates.id',
                'vat_rates.title',
                'vat_rates.rate',
            ]);

        $this->applyContextFilters($query, $request);

        return $query
            ->orderByDesc('total')
            ->orderBy('goods.name')
            ->limit(300)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'denominator' => $row->denominator !== null ? (float) $row->denominator : null,
                'measure_id' => $row->measure_id !== null ? (int) $row->measure_id : null,
                'measure_name' => $row->measure_name,
                'sales_count' => (int) $row->sales_count,
                'quantity' => (float) $row->quantity,
                'average_price' => (float) $row->average_price,
                'total' => (float) $row->total,
                'last_sale_date' => $row->last_sale_date,
                'vat_rate' => $row->vat_rate_id ? [
                    'id' => (int) $row->vat_rate_id,
                    'title' => $row->vat_rate_title,
                    'rate' => (float) $row->vat_rate_rate,
                ] : null,
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
            'payment_reference' => $sale->payment_reference,
            'total' => (float) $sale->total,
            'payment_status' => $sale->payment_status,
            'paid_amount' => (float) $sale->paid_amount,
            'outstanding_amount' => (float) $sale->outstanding_amount,
            'overpaid_amount' => (float) $sale->overpaid_amount,
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
                    'measure_id' => $good->measure_id,
                    'unit_weight_kg' => $good->unit_weight_kg,
                    'measurement' => $good->measurement(),
                    'vat_rate' => $good->vatRate ? [
                        'id' => $good->vatRate->id,
                        'title' => $good->vatRate->title,
                        'rate' => $good->vatRate->rate,
                    ] : null,
                    'pivot' => [
                        'id' => (int) $good->pivot->id,
                        'quantity' => (float) $good->pivot->quantity,
                        'measure_id' => (int) $good->pivot->measure_id > 0 ? (int) $good->pivot->measure_id : null,
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
