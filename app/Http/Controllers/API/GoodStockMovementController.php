<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoodStockMovementResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoodStockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 250), 1), 500);

        $movements = GoodStockMovement::query()
            ->with(['warehouse', 'good:id,name,slug,ava_image,ava_thumb,is_published', 'measure'])
            ->when(
                $request->filled('warehouse_id'),
                fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id'))
            )
            ->when(
                $request->filled('good_id'),
                fn ($query) => $query->where('good_id', $request->input('good_id'))
            )
            ->when(
                $request->filled('type'),
                fn ($query) => $query->where('type', $request->input('type'))
            )
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json(
            GoodStockMovementResource::collection($movements)->resolve($request)
        );
    }

    public function stock(Request $request): JsonResponse
    {
        $rows = DB::table('good_stock_movements')
            ->select([
                'warehouse_id',
                'good_id',
                'measure_id',
            ])
            ->selectRaw('SUM(quantity_delta) as quantity')
            ->selectRaw('SUM(quantity_delta * unit_price) as stock_value')
            ->selectRaw('MAX(moved_at) as last_moved_at')
            ->when(
                $request->filled('warehouse_id'),
                fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id'))
            )
            ->when(
                $request->filled('good_id'),
                fn ($query) => $query->where('good_id', $request->input('good_id'))
            )
            ->groupBy('warehouse_id', 'good_id', 'measure_id')
            ->havingRaw('ABS(SUM(quantity_delta)) > 0.000001')
            ->orderBy('warehouse_id')
            ->orderBy('good_id')
            ->get();

        $warehouses = Warehouse::query()
            ->whereIn('id', $rows->pluck('warehouse_id')->unique())
            ->get()
            ->keyBy('id');

        $goods = Good::query()
            ->whereIn('id', $rows->pluck('good_id')->unique())
            ->get(['id', 'name', 'slug', 'ava_image', 'ava_thumb', 'is_published'])
            ->keyBy('id');

        $measures = Measure::query()
            ->whereIn('id', $rows->pluck('measure_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return response()->json($rows->map(fn ($row) => [
            'warehouse_id' => $row->warehouse_id,
            'good_id' => $row->good_id,
            'measure_id' => $row->measure_id,
            'quantity' => (float) $row->quantity,
            'stock_value' => (float) $row->stock_value,
            'last_moved_at' => $row->last_moved_at,
            'warehouse' => $warehouses->has($row->warehouse_id)
                ? new WarehouseResource($warehouses->get($row->warehouse_id))
                : null,
            'good' => $goods->has($row->good_id) ? [
                'id' => $goods->get($row->good_id)->id,
                'name' => $goods->get($row->good_id)->name,
                'slug' => $goods->get($row->good_id)->slug,
                'ava_image' => $goods->get($row->good_id)->ava_image,
                'ava_thumb' => $goods->get($row->good_id)->ava_thumb,
                'is_published' => (bool) $goods->get($row->good_id)->is_published,
            ] : null,
            'measure' => $measures->has($row->measure_id) ? [
                'id' => $measures->get($row->measure_id)->id,
                'name' => $measures->get($row->measure_id)->name,
            ] : null,
        ])->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['quantity_delta'] = $this->quantityDelta($data['type'], $data['quantity']);
        $data['unit_price'] ??= 0;
        unset($data['quantity']);

        $movement = GoodStockMovement::create($data);

        return response()->json(
            new GoodStockMovementResource(
                $movement->load(['warehouse', 'good:id,name,slug,ava_image,ava_thumb,is_published', 'measure'])
            ),
            201
        );
    }

    public function update(
        Request $request,
        GoodStockMovement $goodStockMovement
    ): GoodStockMovementResource {
        $data = $this->validated($request);
        $data['quantity_delta'] = $this->quantityDelta($data['type'], $data['quantity']);
        $data['unit_price'] ??= 0;
        unset($data['quantity']);

        $goodStockMovement->update($data);

        return new GoodStockMovementResource(
            $goodStockMovement->fresh([
                'warehouse',
                'good:id,name,slug,ava_image,ava_thumb,is_published',
                'measure',
            ])
        );
    }

    public function destroy(GoodStockMovement $goodStockMovement): JsonResponse
    {
        $goodStockMovement->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'good_id' => ['required', 'exists:goods,id'],
            'measure_id' => ['nullable', 'exists:measures,id'],
            'type' => ['required', Rule::in([
                GoodStockMovement::TYPE_RECEIPT,
                GoodStockMovement::TYPE_WRITE_OFF,
                GoodStockMovement::TYPE_ADJUSTMENT,
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
            GoodStockMovement::TYPE_WRITE_OFF => -abs($quantity),
            GoodStockMovement::TYPE_RECEIPT => abs($quantity),
            default => $quantity,
        };
    }
}
