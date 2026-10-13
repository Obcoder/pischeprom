<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\SupplierOrders\SupplierOrderContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SupplierOrderContextController extends Controller
{
    public function show(Request $request, Unit $unit, SupplierOrderContext $context): JsonResponse
    {
        if (in_array($request->input('include_deferred'), ['true', 'false'], true)) {
            $request->merge(['include_deferred' => $request->input('include_deferred') === 'true']);
        }
        $input = $request->validate([
            'tab' => ['sometimes', Rule::in(['demand', 'assortment', 'catalog'])],
            'search' => ['nullable', 'string', 'max:200'], 'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'warehouse_id' => ['sometimes', 'integer', 'min:0', Rule::when((int) $request->input('warehouse_id') !== 0, ['exists:warehouses,id'])],
            'include_deferred' => ['sometimes', 'boolean'], 'delivery_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([...$context->forUnit($unit, $input),
            'can_send' => Gate::forUser($request->user())->allows('sendMail', $unit),
        ])->header('Cache-Control', 'no-store, private');
    }
}
