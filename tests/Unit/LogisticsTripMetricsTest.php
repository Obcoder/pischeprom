<?php

namespace Tests\Unit;

use App\Models\LogisticsExpenseCategory;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripExpense;
use App\Models\Vehicle;
use App\Services\Logistics\TripMetricsService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LogisticsTripMetricsTest extends TestCase
{
    public function test_zero_distance_and_weight_never_cause_division_by_zero(): void
    {
        $trip = $this->tripWithExpenses([
            $this->expense('other', 'RUB', 100),
        ], actualDistanceM: 0, plannedDistanceM: 0, cargoWeightKg: 0);

        $metrics = app(TripMetricsService::class)->calculate($trip);

        $this->assertSame(100.0, $metrics['total_expenses']);
        $this->assertNull($metrics['distance_basis']);
        $this->assertNull($metrics['cost_per_km']);
        $this->assertNull($metrics['cost_per_kg']);
        $this->assertNull($metrics['cost_per_ton_km']);
        $this->assertNull($metrics['actual_fuel_consumption_l_per_100km']);
    }

    public function test_different_currencies_are_grouped_and_not_summed_together(): void
    {
        $trip = $this->tripWithExpenses([
            $this->expense('fuel', 'RUB', 120, 30, 'l'),
            $this->expense('toll_road', 'EUR', 10),
        ], actualDistanceM: 100_000, plannedDistanceM: 90_000, cargoWeightKg: 1_000);

        $metrics = app(TripMetricsService::class)->calculate($trip);

        $this->assertTrue($metrics['has_multiple_currencies']);
        $this->assertNull($metrics['total_expenses']);
        $this->assertSame(['EUR' => 10.0, 'RUB' => 120.0], $metrics['totals_by_currency']);
        $this->assertNull($metrics['cost_per_km']);
        $this->assertSame(30.0, $metrics['fuel_liters']);
        $this->assertSame(30.0, $metrics['actual_fuel_consumption_l_per_100km']);
        $this->assertSame(10_000, $metrics['distance_deviation_m']);
    }

    /** @param list<LogisticsTripExpense> $expenses */
    private function tripWithExpenses(
        array $expenses,
        int $actualDistanceM,
        int $plannedDistanceM,
        int $cargoWeightKg,
    ): LogisticsTrip {
        $trip = new LogisticsTrip([
            'actual_distance_m' => $actualDistanceM,
            'planned_distance_m' => $plannedDistanceM,
            'cargo_weight_kg' => $cargoWeightKg,
        ]);
        $trip->setRelation('expenses', new Collection($expenses));
        $trip->setRelation('vehicle', new Vehicle(['payload_capacity_kg' => 10_000]));

        return $trip;
    }

    private function expense(
        string $categoryCode,
        string $currency,
        float $amount,
        ?float $quantity = null,
        ?string $unit = null,
    ): LogisticsTripExpense {
        $expense = new LogisticsTripExpense([
            'currency_code' => $currency,
            'allocated_amount' => $amount,
            'quantity' => $quantity,
            'unit' => $unit,
        ]);
        $expense->setRelation('category', new LogisticsExpenseCategory(['code' => $categoryCode]));

        return $expense;
    }
}
