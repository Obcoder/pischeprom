<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxiShiftResource;
use App\Models\TaxiShift;
use Illuminate\Http\Request;

class TaxiShiftController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = TaxiShift::query()
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date', '<=', $request->input('date_to')));

        $totalRevenue = (clone $baseQuery)->sum('revenue_amount');
        $totalOrders = (clone $baseQuery)->sum('orders_count');

        $sortBy = in_array($request->input('sort_by'), ['date', 'orders_count', 'revenue_amount', 'id'], true)
            ? $request->input('sort_by')
            : 'date';

        $direction = filter_var($request->input('sort_desc', true), FILTER_VALIDATE_BOOLEAN)
            ? 'desc'
            : 'asc';

        $query = (clone $baseQuery)->orderBy($sortBy, $direction);

        if ($sortBy !== 'date') {
            $query->orderByDesc('date');
        }

        $shifts = $query
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => TaxiShiftResource::collection($shifts)->resolve($request),
            'meta' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => (int) $totalOrders,
                'count' => $shifts->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $taxiShift = TaxiShift::create($this->validated($request));

        return response()->json(new TaxiShiftResource($taxiShift), 201);
    }

    public function show(TaxiShift $taxiShift)
    {
        return new TaxiShiftResource($taxiShift);
    }

    public function update(Request $request, TaxiShift $taxiShift)
    {
        $taxiShift->update($this->validated($request, true));

        return new TaxiShiftResource($taxiShift->fresh());
    }

    public function destroy(TaxiShift $taxiShift)
    {
        $taxiShift->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $dateRule = $partial ? 'sometimes' : 'required';
        $revenueRule = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'date' => [$dateRule, 'date'],
            'orders_count' => ['nullable', 'integer', 'min:0'],
            'revenue_amount' => [$revenueRule, 'numeric', 'min:0'],
        ]);

        if (array_key_exists('orders_count', $data)) {
            $data['orders_count'] = (int) ($data['orders_count'] ?? 0);
        }

        return $data;
    }
}
