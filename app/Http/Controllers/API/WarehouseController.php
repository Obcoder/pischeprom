<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::query()
            ->when(
                ! $request->boolean('include_goods'),
                fn ($query) => $query->where(function ($query) {
                    $query
                        ->whereNull('code')
                        ->orWhere('code', '!=', Warehouse::GOODS_CODE);
                })
            )
            ->when($request->has('is_active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(WarehouseResource::collection($warehouses)->resolve($request));
    }

    public function store(Request $request)
    {
        $warehouse = Warehouse::create($this->validated($request));

        return response()->json(new WarehouseResource($warehouse), 201);
    }

    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $this->validated($request, $warehouse);
        $warehouse = DB::transaction(function () use ($warehouse, $data): Warehouse {
            $warehouse = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $warehouse->code === Warehouse::GOODS_CODE
                    && array_key_exists('code', $data)
                    && $data['code'] !== Warehouse::GOODS_CODE,
                422,
                'Код системного склада goods нельзя изменить.'
            );

            $warehouse->update($data);

            return $warehouse;
        }, 3);

        return new WarehouseResource($warehouse->fresh());
    }

    public function destroy(Warehouse $warehouse)
    {
        DB::transaction(function () use ($warehouse): void {
            $warehouse = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $warehouse->code === Warehouse::GOODS_CODE,
                422,
                'Системный склад goods нельзя удалить.'
            );
            abort_if(
                $warehouse->goodStockMovements()->exists(),
                422,
                'Нельзя удалить склад с движениями товаров. Сначала выполните сверку складского учёта.'
            );

            $warehouse->delete();
        }, 3);

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('warehouses', 'code')->ignore($warehouse?->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
