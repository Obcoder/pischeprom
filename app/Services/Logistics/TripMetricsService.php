<?php

namespace App\Services\Logistics;

use App\Models\LogisticsTrip;

class TripMetricsService
{
    public function calculate(LogisticsTrip $trip): array
    {
        $trip->loadMissing(['expenses.category', 'vehicle']);

        $totals = $trip->expenses
            ->groupBy('currency_code')
            ->map(fn ($expenses) => round((float) $expenses->sum('allocated_amount'), 2))
            ->sortKeys();

        $byCategory = $trip->expenses
            ->groupBy(fn ($expense) => $expense->category?->code ?? 'unknown')
            ->map(fn ($expenses) => $expenses
                ->groupBy('currency_code')
                ->map(fn ($currencyExpenses) => round((float) $currencyExpenses->sum('allocated_amount'), 2))
                ->sortKeys()
                ->all())
            ->sortKeys();

        $currency = $totals->count() === 1 ? (string) $totals->keys()->first() : null;
        $total = $currency ? (float) $totals->first() : null;
        $actualDistance = (int) ($trip->actual_distance_m ?? 0);
        $plannedDistance = (int) ($trip->planned_distance_m ?? 0);
        $distanceM = $actualDistance > 0 ? $actualDistance : ($plannedDistance > 0 ? $plannedDistance : null);
        $distanceBasis = $actualDistance > 0 ? 'actual' : ($plannedDistance > 0 ? 'planned' : null);
        $distanceKm = $distanceM ? $distanceM / 1000 : null;
        $weightKg = (float) ($trip->cargo_weight_kg ?? 0);

        $fuelExpenses = $trip->expenses->filter(fn ($expense) => $expense->category?->code === 'fuel');
        $fuelLiters = (float) $fuelExpenses
            ->filter(fn ($expense) => in_array(mb_strtolower((string) $expense->unit), ['l', 'л', 'liter', 'litre'], true))
            ->sum('quantity');
        $fuelCost = $fuelExpenses
            ->groupBy('currency_code')
            ->map(fn ($expenses) => round((float) $expenses->sum('allocated_amount'), 2))
            ->sortKeys();

        return [
            'totals_by_currency' => $totals->all(),
            'expenses_by_category' => $byCategory->all(),
            'has_multiple_currencies' => $totals->count() > 1,
            'primary_currency' => $currency,
            'total_expenses' => $total,
            'distance_basis' => $distanceBasis,
            'cost_per_km' => $this->divide($total, $distanceKm),
            'cost_per_kg' => $this->divide($total, $weightKg),
            'cost_per_ton_km' => $this->divide($total, $distanceKm && $weightKg > 0 ? $distanceKm * ($weightKg / 1000) : null),
            'fuel_liters' => round($fuelLiters, 3),
            'fuel_cost_by_currency' => $fuelCost->all(),
            'actual_fuel_consumption_l_per_100km' => $actualDistance > 0 && $fuelLiters > 0
                ? round($fuelLiters / ($actualDistance / 1000) * 100, 3)
                : null,
            'distance_deviation_m' => $actualDistance > 0 && $plannedDistance > 0
                ? $actualDistance - $plannedDistance
                : null,
            'distance_deviation_percent' => $actualDistance > 0 && $plannedDistance > 0
                ? round(($actualDistance - $plannedDistance) / $plannedDistance * 100, 2)
                : null,
            'arrival_deviation_s' => $trip->actual_arrival_at && $trip->planned_arrival_at
                ? $trip->planned_arrival_at->diffInSeconds($trip->actual_arrival_at, false)
                : null,
            'departure_deviation_s' => $trip->actual_departure_at && $trip->planned_departure_at
                ? $trip->planned_departure_at->diffInSeconds($trip->actual_departure_at, false)
                : null,
            'vehicle_load_factor' => $weightKg > 0 && (float) ($trip->vehicle?->payload_capacity_kg ?? 0) > 0
                ? round($weightKg / (float) $trip->vehicle->payload_capacity_kg, 4)
                : null,
        ];
    }

    private function divide(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator, 4);
    }
}
