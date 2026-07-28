<?php

namespace App\Http\Controllers\API\Logistics;

use App\Enums\Logistics\TripStatus;
use App\Enums\Logistics\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsTrip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LogisticsDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $dateFrom = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->startOfMonth();
        $dateTo = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfMonth();
        $periodTrips = LogisticsTrip::query()
            ->whereBetween('planned_departure_at', [$dateFrom, $dateTo]);
        $tripStats = (clone $periodTrips)
            ->leftJoin('logistics_vehicles', 'logistics_vehicles.id', '=', 'logistics_trips.vehicle_id')
            ->selectRaw('COUNT(logistics_trips.id) as trip_count')
            ->selectRaw('COALESCE(SUM(logistics_trips.planned_distance_m), 0) as planned_distance_m')
            ->selectRaw('COALESCE(SUM(logistics_trips.actual_distance_m), 0) as actual_distance_m')
            ->selectRaw('COALESCE(SUM(logistics_trips.cargo_weight_kg), 0) as cargo_weight_kg')
            ->selectRaw('SUM(CASE WHEN logistics_trips.vehicle_id IS NULL THEN 1 ELSE 0 END) as without_vehicle')
            ->selectRaw('SUM(CASE WHEN logistics_trips.route_calculated_at IS NULL THEN 1 ELSE 0 END) as without_route')
            ->selectRaw('AVG(CASE WHEN logistics_vehicles.payload_capacity_kg > 0 AND logistics_trips.cargo_weight_kg IS NOT NULL THEN logistics_trips.cargo_weight_kg / logistics_vehicles.payload_capacity_kg ELSE NULL END) as average_load_factor')
            ->first();
        $expenseRows = DB::table('logistics_trip_expenses')
            ->join('logistics_trips', 'logistics_trips.id', '=', 'logistics_trip_expenses.trip_id')
            ->join('logistics_expense_categories', 'logistics_expense_categories.id', '=', 'logistics_trip_expenses.expense_category_id')
            ->whereNull('logistics_trips.deleted_at')
            ->whereBetween('logistics_trips.planned_departure_at', [$dateFrom, $dateTo])
            ->selectRaw('logistics_trip_expenses.currency_code, logistics_expense_categories.code as category_code, SUM(logistics_trip_expenses.allocated_amount) as total')
            ->groupBy('logistics_trip_expenses.currency_code', 'logistics_expense_categories.code')
            ->get();
        $expenseTotals = $expenseRows->groupBy('currency_code')->map(fn ($rows) => round((float) $rows->sum('total'), 2));
        $categoryTotals = $expenseRows->groupBy('category_code')->map(fn ($rows) => $rows
            ->mapWithKeys(fn ($row) => [$row->currency_code => round((float) $row->total, 2)]));
        $distanceBasisM = (int) $tripStats->actual_distance_m > 0
            ? (int) $tripStats->actual_distance_m
            : (int) $tripStats->planned_distance_m;
        $costPerKm = $expenseTotals->map(fn ($total) => $distanceBasisM > 0
            ? round($total / ($distanceBasisM / 1000), 2)
            : null);
        $costPerKg = $expenseTotals->map(fn ($total) => (float) $tripStats->cargo_weight_kg > 0
            ? round($total / (float) $tripStats->cargo_weight_kg, 2)
            : null);
        $expensesWithoutCheck = DB::table('logistics_trip_expenses')
            ->join('logistics_trips', 'logistics_trips.id', '=', 'logistics_trip_expenses.trip_id')
            ->whereNull('logistics_trips.deleted_at')
            ->whereNull('logistics_trip_expenses.check_id')
            ->whereBetween('logistics_trips.planned_departure_at', [$dateFrom, $dateTo])
            ->count();

        return response()->json(['data' => [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'trips' => [
                'active' => LogisticsTrip::query()->whereIn('status', [
                    TripStatus::Planned->value,
                    TripStatus::InProgress->value,
                ])->count(),
                'draft' => LogisticsTrip::query()->where('status', TripStatus::Draft->value)->count(),
                'completed' => (clone $periodTrips)->where('status', TripStatus::Completed->value)->count(),
                'total' => (int) $tripStats->trip_count,
                'without_vehicle' => (int) $tripStats->without_vehicle,
                'without_route' => (int) $tripStats->without_route,
            ],
            'vehicles' => [
                'active' => Vehicle::query()->where('status', VehicleStatus::Active->value)->count(),
                'maintenance' => Vehicle::query()->where('status', VehicleStatus::Maintenance->value)->count(),
            ],
            'planned_distance_m' => (int) $tripStats->planned_distance_m,
            'actual_distance_m' => (int) $tripStats->actual_distance_m,
            'cargo_weight_kg' => round((float) $tripStats->cargo_weight_kg, 3),
            'average_vehicle_load_factor' => $tripStats->average_load_factor !== null
                ? round((float) $tripStats->average_load_factor, 4)
                : null,
            'expenses_without_check' => $expensesWithoutCheck,
            'expenses_by_currency' => $expenseTotals,
            'expenses_by_category' => $categoryTotals,
            'cost_per_km_by_currency' => $costPerKm,
            'cost_per_kg_by_currency' => $costPerKg,
            'distance_basis' => (int) $tripStats->actual_distance_m > 0 ? 'actual' : 'planned',
            'recent_trips' => LogisticsTrip::query()
                ->with(['vehicle:id,registration_number', 'stops.city:id,name'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (LogisticsTrip $trip) => [
                    'id' => $trip->id,
                    'number' => $trip->number,
                    'status' => $trip->status->value,
                    'vehicle' => $trip->vehicle?->registration_number,
                    'route' => $trip->stops->pluck('city.name')->filter()->join(' → '),
                    'planned_departure_at' => $trip->planned_departure_at?->toISOString(),
                ]),
        ]]);
    }
}
