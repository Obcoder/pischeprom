<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Suppliers\SupplierAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierAnalyticsController extends Controller
{
    public function show(Request $request, Unit $unit, SupplierAnalytics $analytics): JsonResponse
    {
        $input = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
            'goods_page' => ['nullable', 'integer', 'min:1'], 'purchases_page' => ['nullable', 'integer', 'min:1'],
            'goods_search' => ['nullable', 'string', 'max:150'], 'goods_source' => ['nullable', Rule::in(['all', 'orders', 'purchases'])],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        return response()->json($analytics->show($unit, $input))->header('Cache-Control', 'no-store, private');
    }
}
