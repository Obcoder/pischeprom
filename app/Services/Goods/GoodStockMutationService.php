<?php

namespace App\Services\Goods;

use App\Models\Good;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodStockMutationService
{
    // Smaller than the minimum supported quantity (0.000001).
    private const EPSILON = 0.000000001;

    /**
     * All stock writers must lock goods before reading or changing movements.
     * Locking an existing Good also serializes the first movement for a bucket.
     */
    public function run(array $goodIds, callable $callback, bool $allowNegativeStock = false): mixed
    {
        $goodIds = collect($goodIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return DB::transaction(function () use ($goodIds, $callback, $allowNegativeStock): mixed {
            $lockedGoods = Good::query()
                ->whereIn('id', $goodIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($lockedGoods->count() !== count($goodIds)) {
                throw ValidationException::withMessages([
                    'goods' => 'Один из товаров больше не существует. Обновите страницу и повторите операцию.',
                ]);
            }

            $before = $this->balances($goodIds);
            $result = $callback();
            $after = $this->balances($goodIds);

            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                $previous = $before[$key] ?? 0.0;
                $current = $after[$key] ?? 0.0;

                if (! is_finite($previous) || ! is_finite($current)) {
                    throw ValidationException::withMessages([
                        'goods' => 'Количество товара выходит за допустимые пределы.',
                    ]);
                }

                if (
                    ! $allowNegativeStock
                    && $current < -self::EPSILON
                    && $current < $previous - self::EPSILON
                ) {
                    [$warehouseId, $goodId, $measureId] = explode(':', $key);
                    $measure = $measureId === 'none' ? 'без единицы измерения' : "единица #{$measureId}";

                    throw ValidationException::withMessages([
                        'goods' => "Недостаточно товара #{$goodId} на складе #{$warehouseId} ({$measure}). "
                            .'Остаток: '.$this->formatQuantity($previous)
                            .', списание: '.$this->formatQuantity($previous - $current).'.',
                    ]);
                }
            }

            return $result;
        }, 3);
    }

    private function balances(array $goodIds): array
    {
        $balances = [];

        // A locking read sees committed concurrent writes under MySQL REPEATABLE READ.
        // SUM() on a normal snapshot could still see the balance before the Good lock.
        $movements = DB::table('good_stock_movements')
            ->whereIn('good_id', $goodIds)
            ->orderBy('good_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['warehouse_id', 'good_id', 'measure_id', 'quantity_delta']);

        foreach ($movements as $movement) {
            $key = $movement->warehouse_id.':'.$movement->good_id.':'.($movement->measure_id ?? 'none');
            $balances[$key] = ($balances[$key] ?? 0.0) + (float) $movement->quantity_delta;
        }

        return $balances;
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 6, '.', ''), '0'), '.');
    }
}
