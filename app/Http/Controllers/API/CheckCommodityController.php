<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckCommodityResource;
use App\Models\Check;
use App\Models\CheckCommodity;
use App\Models\Commodity;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckCommodityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ?Check $check = null)
    {
        $data = $this->validated($request, $check);
        $check = $check ?: Check::findOrFail($data['check_id']);
        $commodity = Commodity::findOrFail($data['commodity_id']);

        $item = DB::transaction(function () use ($check, $commodity, $data) {
            $item = CheckCommodity::create([
                'check_id' => $check->id,
                'commodity_id' => $commodity->id,
                'warehouse_id' => $this->resolveWarehouseId($data['warehouse_id'] ?? null),
                'quantity' => $data['quantity'] ?? 1,
                'measure_id' => $data['measure_id'] ?? null,
                'expense_article_id' => $data['expense_article_id'] ?? $commodity->expense_article_id,
                'price' => $data['price'] ?? 0,
            ]);

            $this->syncStockMovement($item);

            return $item->fresh($this->relations());
        });

        return response()->json(new CheckCommodityResource($item), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CheckCommodity $checkCommodity)
    {
        $data = $request->validate([
            'commodity_id' => ['sometimes', 'required', 'exists:commodities,id'],
            'warehouse_id' => ['sometimes', 'nullable', 'exists:warehouses,id'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'measure_id' => ['sometimes', 'nullable', 'exists:measures,id'],
            'expense_article_id' => ['sometimes', 'nullable', 'exists:expense_articles,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $item = DB::transaction(function () use ($checkCommodity, $data) {
            if (array_key_exists('warehouse_id', $data)) {
                $data['warehouse_id'] = $this->resolveWarehouseId($data['warehouse_id']);
            }

            if (array_key_exists('commodity_id', $data) && ! array_key_exists('expense_article_id', $data)) {
                $commodity = Commodity::findOrFail($data['commodity_id']);
                $data['expense_article_id'] = $commodity->expense_article_id;
            }

            $checkCommodity->update($data);
            $this->syncStockMovement($checkCommodity->fresh(['check']));

            return $checkCommodity->fresh($this->relations());
        });

        return new CheckCommodityResource($item);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CheckCommodity $checkCommodity)
    {
        DB::transaction(function () use ($checkCommodity) {
            StockMovement::query()
                ->where('source_type', StockMovement::SOURCE_CHECK_COMMODITY)
                ->where('source_id', $checkCommodity->id)
                ->delete();
            $checkCommodity->delete();
        });

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Check $check = null): array
    {
        return $request->validate([
            'check_id' => [$check ? 'nullable' : 'required', 'exists:checks,id'],
            'commodity_id' => ['required', 'exists:commodities,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'measure_id' => ['nullable', 'exists:measures,id'],
            'expense_article_id' => ['nullable', 'exists:expense_articles,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function resolveWarehouseId(mixed $warehouseId = null): int
    {
        if ($warehouseId) {
            return (int) $warehouseId;
        }

        $warehouse = Warehouse::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($warehouse) {
            return $warehouse->id;
        }

        $warehouse = Warehouse::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($warehouse) {
            return $warehouse->id;
        }

        return Warehouse::create([
            'name' => 'Основной склад',
            'code' => 'main',
            'is_active' => true,
            'sort_order' => 100,
        ])->id;
    }

    private function syncStockMovement(CheckCommodity $item): void
    {
        $item->loadMissing('check');

        StockMovement::query()->updateOrCreate(
            [
                'source_type' => StockMovement::SOURCE_CHECK_COMMODITY,
                'source_id' => $item->id,
            ],
            [
                'warehouse_id' => $this->resolveWarehouseId($item->warehouse_id),
                'commodity_id' => $item->commodity_id,
                'measure_id' => $item->measure_id,
                'type' => StockMovement::TYPE_CHECK_PURCHASE,
                'quantity_delta' => $item->quantity,
                'unit_price' => $item->price,
                'moved_at' => optional($item->check?->date)->toDateString() ?: now()->toDateString(),
                'note' => "Check #{$item->check_id}",
            ]
        );
    }

    private function relations(): array
    {
        return [
            'commodity.avaMedia',
            'commodity.expenseArticle',
            'commodity.project',
            'expenseArticle',
            'measure',
            'warehouse',
        ];
    }
}
