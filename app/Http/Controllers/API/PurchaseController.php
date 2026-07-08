<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class PurchaseController extends Controller
{
    private const PER_PAGE = 100;

    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request)
    {
        $page = max((int) $request->integer('page', 1), 1);
        $sortBy = $request->string('sort_by')->toString() ?: 'date';
        $sortDesc = filter_var($request->input('sort_desc', true), FILTER_VALIDATE_BOOLEAN);

        $purchases = Purchase::query()
            ->without(['entity', 'goods'])
            ->with($this->purchaseRelations())
            ->withCount('goods')
            ->when($request->filled('search'), fn (Builder $query) => $this->applySearch(
                $query,
                trim($request->string('search')->toString())
            ))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('date', '<=', $request->date('date_to')))
            ->when($request->filled('amount_from'), fn (Builder $query) => $query->where('amount', '>=', (float) $request->input('amount_from')))
            ->when($request->filled('amount_to'), fn (Builder $query) => $query->where('amount', '<=', (float) $request->input('amount_to')));

        $this->applyRelationFilters($purchases, $request);
        $this->applySort($purchases, $sortBy, $sortDesc);

        $purchases = $purchases->paginate(
            perPage: self::PER_PAGE,
            columns: ['purchases.*'],
            pageName: 'page',
            page: $page
        );

        return PurchaseResource::collection($purchases);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        $purchase = Purchase::query()
            ->without(['entity', 'goods'])
            ->with($this->purchaseRelations())
            ->withCount('goods')
            ->findOrFail($purchase->id);

        return new PurchaseResource($purchase);
    }

    public function store(StorePurchaseRequest $request): PurchaseResource
    {
        $purchase = $this->purchaseService->store($request->validated());

        return new PurchaseResource($this->findForResponse($purchase->id));
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): PurchaseResource
    {
        $purchase = $this->purchaseService->update($purchase, $request->validated());

        return new PurchaseResource($this->findForResponse($purchase->id));
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $purchase->delete();

        return response()->json([
                                    'message' => 'Закупка удалена',
                                ]);
    }

    private function findForResponse(int $id): Purchase
    {
        return Purchase::query()
            ->without(['entity', 'goods'])
            ->with($this->purchaseRelations())
            ->withCount('goods')
            ->findOrFail($id);
    }

    private function purchaseRelations(): array
    {
        $entityColumns = $this->existingColumns('entities', [
            'id',
            'name',
            'full_name',
            'INN',
            'KPP',
            'OGRN',
        ]);
        $goodColumns = $this->existingColumns('goods', [
            'id',
            'name',
            'slug',
            'ava_image',
            'ava_thumb',
        ]);
        $unitColumns = $this->existingColumns('units', [
            'id',
            'name',
            'is_customer',
            'is_supplier',
        ]);

        return [
            'entity' => fn ($query) => $query
                ->without(['buildings', 'classification', 'country'])
                ->select(array_map(fn (string $column) => "entities.{$column}", $entityColumns))
                ->with([
                    'units' => fn ($unitQuery) => $unitQuery
                        ->without(['fields', 'labels', 'telephones', 'uris'])
                        ->select(array_map(fn (string $column) => "units.{$column}", $unitColumns)),
                ]),
            'goods' => fn ($query) => $query
                ->select(array_map(fn (string $column) => "goods.{$column}", $goodColumns)),
        ];
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = "%{$search}%";
        $entityColumns = $this->existingColumns('entities', ['name', 'full_name', 'INN', 'KPP', 'OGRN']);
        $goodColumns = $this->existingColumns('goods', ['name', 'slug']);
        $goodPurchaseColumns = $this->existingColumns('good_purchase', ['quantity', 'price', 'total']);
        $currencyColumns = $this->existingColumns('currencies', ['name', 'code']);

        $query->where(function (Builder $q) use ($like, $entityColumns, $goodColumns, $goodPurchaseColumns, $currencyColumns) {
            $q->where('purchases.id', 'like', $like)
                ->orWhere('purchases.date', 'like', $like)
                ->orWhere('purchases.amount', 'like', $like)
                ->orWhereHas('entity', function (Builder $entityQuery) use ($like, $entityColumns) {
                    $entityQuery->where(function (Builder $entitySearch) use ($like, $entityColumns) {
                        foreach ($entityColumns as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $entitySearch->{$method}("entities.{$column}", 'like', $like);
                        }

                        $entitySearch->orWhereHas('units', fn (Builder $unitQuery) => $unitQuery->where('units.name', 'like', $like));
                    });
                })
                ->orWhereHas('goods', function (Builder $goodsQuery) use ($like, $goodColumns, $goodPurchaseColumns) {
                    $goodsQuery->where(function (Builder $goodsSearch) use ($like, $goodColumns, $goodPurchaseColumns) {
                        foreach ($goodColumns as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $goodsSearch->{$method}("goods.{$column}", 'like', $like);
                        }

                        foreach ($goodPurchaseColumns as $column) {
                            $goodsSearch->orWhere("good_purchase.{$column}", 'like', $like);
                        }
                    });
                })
                ->orWhereExists(function ($subQuery) use ($like) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('good_purchase')
                        ->join('measures', 'measures.id', '=', 'good_purchase.measure_id')
                        ->whereColumn('good_purchase.purchase_id', 'purchases.id')
                        ->where('measures.name', 'like', $like);
                })
                ->orWhereExists(function ($subQuery) use ($like, $currencyColumns) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('good_purchase')
                        ->join('currencies', 'currencies.id', '=', 'good_purchase.currency_id')
                        ->whereColumn('good_purchase.purchase_id', 'purchases.id')
                        ->where(function ($currencyQuery) use ($like, $currencyColumns) {
                            foreach ($currencyColumns as $index => $column) {
                                $method = $index === 0 ? 'where' : 'orWhere';
                                $currencyQuery->{$method}("currencies.{$column}", 'like', $like);
                            }
                        });
                });
        });
    }

    private function applyRelationFilters(Builder $query, Request $request): void
    {
        $entityIds = $this->integerArray($request->input('entity_ids', []));
        $unitIds = $this->integerArray($request->input('unit_ids', []));
        $goodIds = $this->integerArray($request->input('good_ids', []));
        $measureIds = $this->integerArray($request->input('measure_ids', []));
        $currencyIds = $this->integerArray($request->input('currency_ids', []));

        $query
            ->when($entityIds, fn (Builder $q) => $q->whereIn('entity_id', $entityIds))
            ->when($unitIds, fn (Builder $q) => $q->whereHas('entity.units', fn (Builder $unitQuery) => $unitQuery->whereIn('units.id', $unitIds)))
            ->when($goodIds, fn (Builder $q) => $q->whereHas('goods', fn (Builder $goodsQuery) => $goodsQuery->whereIn('goods.id', $goodIds)))
            ->when($measureIds, fn (Builder $q) => $q->whereHas('goods', fn (Builder $goodsQuery) => $goodsQuery->whereIn('good_purchase.measure_id', $measureIds)))
            ->when($currencyIds, fn (Builder $q) => $q->whereHas('goods', fn (Builder $goodsQuery) => $goodsQuery->whereIn('good_purchase.currency_id', $currencyIds)));
    }

    private function integerArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect(Arr::wrap($value))
            ->filter(fn ($item) => $item !== null && $item !== '')
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function applySort(Builder $query, string $sortBy, bool $sortDesc): void
    {
        $direction = $sortDesc ? 'desc' : 'asc';

        match ($sortBy) {
            'id' => $query->orderBy('purchases.id', $direction),
            'amount' => $query->orderBy('purchases.amount', $direction)->orderByDesc('purchases.date'),
            default => $query->orderBy('purchases.date', $direction)->orderByDesc('purchases.id'),
        };
    }

    private function existingColumns(string $table, array $columns): array
    {
        static $cache = [];

        $available = $cache[$table] ??= array_flip(Schema::getColumnListing($table));

        return array_values(array_filter(
            $columns,
            fn (string $column) => isset($available[$column])
        ));
    }
}
