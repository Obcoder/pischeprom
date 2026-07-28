<?php

namespace App\Services\Logistics;

use App\Models\Check;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripExpenseService
{
    public function create(LogisticsTrip $trip, array $payload, ?int $userId): LogisticsTripExpense
    {
        return DB::transaction(function () use ($trip, $payload, $userId) {
            $payload = $this->withCheckDefaults($payload);
            $this->assertAllocationAvailable($payload);

            return $trip->expenses()->create([
                ...$payload,
                'created_by' => $userId,
                'updated_by' => $userId,
            ])->load(['category', 'check.entity']);
        }, 3);
    }

    public function update(LogisticsTrip $trip, LogisticsTripExpense $expense, array $payload, ?int $userId): LogisticsTripExpense
    {
        if ($expense->trip_id !== $trip->id) {
            abort(404);
        }

        return DB::transaction(function () use ($expense, $payload, $userId) {
            $expense = LogisticsTripExpense::query()->lockForUpdate()->findOrFail($expense->id);
            $payload = $this->withCheckDefaults($payload);
            $this->assertAllocationAvailable($payload, $expense);
            $expense->update([...$payload, 'updated_by' => $userId]);

            return $expense->refresh()->load(['category', 'check.entity']);
        }, 3);
    }

    public function availableForCheck(Check $check, ?LogisticsTripExpense $except = null): float
    {
        $allocated = LogisticsTripExpense::query()
            ->where('check_id', $check->id)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->sum('allocated_amount');

        return max(0, round((float) $check->amount - (float) $allocated, 2));
    }

    private function assertAllocationAvailable(array $payload, ?LogisticsTripExpense $expense = null): void
    {
        if (empty($payload['check_id'])) {
            return;
        }

        $checkCurrency = mb_strtoupper((string) config('logistics.currency_code', 'RUB'));
        if (($payload['currency_code'] ?? $checkCurrency) !== $checkCurrency) {
            throw ValidationException::withMessages([
                'currency_code' => "Чеки без собственной валюты распределяются только в {$checkCurrency}.",
            ]);
        }

        $check = Check::query()->lockForUpdate()->findOrFail($payload['check_id']);
        $available = $this->availableForCheck($check, $expense);

        if ((int) round((float) $payload['allocated_amount'] * 100) > (int) round($available * 100)) {
            throw ValidationException::withMessages([
                'allocated_amount' => sprintf(
                    'Распределение превышает доступный остаток чека: %.2f %s.',
                    $available,
                    config('logistics.currency_code', 'RUB')
                ),
            ]);
        }
    }

    private function withCheckDefaults(array $payload): array
    {
        $payload['currency_code'] = mb_strtoupper((string) ($payload['currency_code'] ?? config('logistics.currency_code', 'RUB')));

        if (! empty($payload['check_id']) && empty($payload['occurred_at'])) {
            $check = Check::query()->findOrFail($payload['check_id']);
            $payload['occurred_at'] = $check->date?->startOfDay();
        }

        return $payload;
    }
}
