<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommodityResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Commodity;
use App\Models\Measure;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->input('limit', 200), 1), 500);

        $movements = StockMovement::query()
            ->with(['warehouse', 'commodity.avaMedia', 'commodity.expenseArticle', 'commodity.project', 'measure'])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
            ->when($request->filled('commodity_id'), fn ($query) => $query->where('commodity_id', $request->input('commodity_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json(StockMovementResource::collection($movements)->resolve($request));
    }

    public function stock(Request $request)
    {
        $rows = DB::table('stock_movements')
            ->select([
                'warehouse_id',
                'commodity_id',
                'measure_id',
            ])
            ->selectRaw('SUM(quantity_delta) as quantity')
            ->selectRaw('SUM(total_price) as stock_value')
            ->selectRaw('MAX(moved_at) as last_moved_at')
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
            ->when($request->filled('commodity_id'), fn ($query) => $query->where('commodity_id', $request->input('commodity_id')))
            ->groupBy('warehouse_id', 'commodity_id', 'measure_id')
            ->havingRaw('ABS(SUM(quantity_delta)) > 0.000001')
            ->orderBy('warehouse_id')
            ->orderBy('commodity_id')
            ->get();

        $warehouses = Warehouse::query()
            ->whereIn('id', $rows->pluck('warehouse_id')->unique())
            ->get()
            ->keyBy('id');

        $commodities = Commodity::query()
            ->with(['avaMedia', 'expenseArticle', 'project'])
            ->whereIn('id', $rows->pluck('commodity_id')->unique())
            ->get()
            ->keyBy('id');

        $measures = Measure::query()
            ->whereIn('id', $rows->pluck('measure_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return response()->json($rows->map(fn ($row) => [
            'warehouse_id' => $row->warehouse_id,
            'commodity_id' => $row->commodity_id,
            'measure_id' => $row->measure_id,
            'quantity' => (float) $row->quantity,
            'stock_value' => (float) $row->stock_value,
            'last_moved_at' => $row->last_moved_at,
            'warehouse' => new WarehouseResource($warehouses->get($row->warehouse_id)),
            'commodity' => $commodities->has($row->commodity_id)
                ? new CommodityResource($commodities->get($row->commodity_id))
                : null,
            'measure' => $measures->has($row->measure_id) ? [
                'id' => $measures->get($row->measure_id)->id,
                'name' => $measures->get($row->measure_id)->name,
            ] : null,
        ])->values());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['quantity_delta'] = $this->quantityDelta($data['type'], $data['quantity']);
        unset($data['quantity']);

        $movement = StockMovement::create($data);

        return response()->json(
            new StockMovementResource($movement->load(['warehouse', 'commodity.avaMedia', 'commodity.expenseArticle', 'commodity.project', 'measure'])),
            201
        );
    }

    public function update(Request $request, StockMovement $stockMovement)
    {
        $this->ensureManualMovement($stockMovement);

        $data = $this->validated($request);
        $data['quantity_delta'] = $this->quantityDelta($data['type'], $data['quantity']);
        unset($data['quantity']);

        $stockMovement->update($data);

        return new StockMovementResource(
            $stockMovement->fresh(['warehouse', 'commodity.avaMedia', 'commodity.expenseArticle', 'commodity.project', 'measure'])
        );
    }

    public function destroy(StockMovement $stockMovement)
    {
        $this->ensureManualMovement($stockMovement);

        $stockMovement->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'commodity_id' => ['required', 'exists:commodities,id'],
            'measure_id' => ['nullable', 'exists:measures,id'],
            'type' => ['required', Rule::in([
                StockMovement::TYPE_RECEIPT,
                StockMovement::TYPE_WRITE_OFF,
                StockMovement::TYPE_ADJUSTMENT,
            ])],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'moved_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function quantityDelta(string $type, mixed $quantity): float
    {
        $quantity = (float) $quantity;

        return match ($type) {
            StockMovement::TYPE_WRITE_OFF => -abs($quantity),
            StockMovement::TYPE_RECEIPT => abs($quantity),
            default => $quantity,
        };
    }

    private function ensureManualMovement(StockMovement $stockMovement): void
    {
        abort_if(
            filled($stockMovement->source_type),
            422,
            'Это движение создано чеком и редактируется через чек.'
        );
    }
}
