<?php

namespace App\Services\Goods;

use App\Models\Good;
use App\Models\Measure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quantity and price always refer to one accounting unit; packaging is descriptive. */
class GoodMeasurement
{
    public function for(Good $good): array
    {
        $measure = $good->measure;

        return [
            'measure_id' => $measure?->id,
            'unit_label' => $measure?->name,
            'kilograms_per_unit' => $measure
                ? ($this->kilograms($measure->name) ?? ($good->unit_weight_kg > 0 ? (float) $good->unit_weight_kg : null))
                : null,
        ];
    }

    public function assertConfigured(Good $good, string $key = 'items'): array
    {
        $measurement = $this->for($good);
        if (! $measurement['measure_id']) {
            throw ValidationException::withMessages([
                $key => "Укажите единицу учёта товара «{$good->name}» в карточке товара (Товароведение).",
            ]);
        }

        return $measurement;
    }

    public function assertMeasure(Good $good, ?int $measureId, string $key = 'measure_id'): int
    {
        $measurement = $this->assertConfigured($good, $key);
        if ($measureId !== null && $measureId !== (int) $measurement['measure_id']) {
            throw ValidationException::withMessages([
                $key => "Количество и цена товара «{$good->name}» должны быть указаны в {$measurement['unit_label']}. Обновите данные товара.",
            ]);
        }

        return (int) $measurement['measure_id'];
    }

    public function kilograms(?string $label): ?float
    {
        return match (mb_strtolower(rtrim(trim((string) $label), '.'))) {
            'кг', 'килограмм', 'килограммы', 'kg', 'kilogram', 'kilograms' => 1.0,
            'т', 'тн', 'тонна', 'тонны', 'тонн', 't', 'ton', 'tons', 'tonne', 'tonnes' => 1000.0,
            'г', 'гр', 'грамм', 'граммы', 'gram', 'grams', 'g' => 0.001,
            default => null,
        };
    }

    public function assertSnapshot(Good $good, ?array $snapshot, string $key = 'measurement'): void
    {
        if ($snapshot === null) {
            return;
        }
        $current = $this->assertConfigured($good, $key);
        $weight = $snapshot['kilograms_per_unit'] ?? null;
        $sameWeight = $weight === null
            ? $current['kilograms_per_unit'] === null
            : $current['kilograms_per_unit'] !== null && is_numeric($weight)
                && abs((float) $weight - $current['kilograms_per_unit']) < 0.0000001;
        if ((int) ($snapshot['measure_id'] ?? 0) !== (int) $current['measure_id'] || ! $sameWeight) {
            throw ValidationException::withMessages([
                $key => "Единица или масса единицы товара «{$good->name}» изменились. Обновите товар перед сохранением.",
            ]);
        }
    }

    /** Change the price basis atomically with the Good; documents retain snapshots. */
    public function update(Good $good, array $attributes, string $prefix = ''): Good
    {
        return DB::transaction(function () use ($good, $attributes, $prefix): Good {
            $locked = Good::query()->lockForUpdate()->findOrFail($good->id);
            $before = $locked->measurement();
            $existingPriceBasis = $attributes['existing_price_basis'] ?? 'selected_unit';
            unset($attributes['existing_price_basis']);
            $locked->fill($attributes);
            if ($locked->isDirty('measure_id')) {
                if (! array_key_exists('unit_weight_kg', $attributes)) {
                    $locked->unit_weight_kg = null;
                }
                $locked->unsetRelation('measure');
                $after = $this->assertConfigured($locked, $prefix.'measure_id');
                $this->assertStockUnit($locked, (int) $after['measure_id'], $prefix.'measure_id');
                $prices = $locked->priceTypeValues()->lockForUpdate()->get();
                // Legacy screens disagreed on the price basis, so the first
                // assignment preserves numbers unless the operator selects kg.
                if (! $before['measure_id'] && $existingPriceBasis !== 'kg') {
                    $prices = collect();
                }
                $fromKg = $before['measure_id'] ? $before['kilograms_per_unit'] : 1.0;
                $toKg = $after['kilograms_per_unit'];
                if ($prices->isNotEmpty() && ($fromKg === null || $toKg === null)) {
                    throw ValidationException::withMessages([
                        $prefix.($fromKg === null ? 'measure_id' : 'unit_weight_kg') => 'Для пересчёта действующих цен укажите массу исходной и новой единицы в кг. Без неё нельзя изменить единицу цены.',
                    ]);
                }
                foreach ($prices as $price) {
                    foreach (['price_net', 'price_gross'] as $field) {
                        if ($price->{$field} !== null) {
                            $value = round((float) $price->{$field} * $toKg / $fromKg, 4);
                            if (! is_finite($value) || $value >= 1e14) {
                                throw ValidationException::withMessages([$prefix.'measure_id' => 'Цена после пересчёта выходит за допустимые пределы.']);
                            }
                            $price->{$field} = $value;
                        }
                    }
                    $price->save();
                }
            }
            $locked->save();
            $good->setRawAttributes($locked->getAttributes(), true);
            $good->unsetRelation('measure');

            return $good;
        });
    }

    private function assertStockUnit(Good $good, int $measureId, string $key): void
    {
        $balances = DB::table('good_stock_movements')->where('good_id', $good->id)
            ->orderBy('id')->lockForUpdate()->get(['warehouse_id', 'measure_id', 'quantity_delta'])
            ->groupBy(fn ($row) => $row->warehouse_id.':'.($row->measure_id ?? 'none'));
        foreach ($balances as $rows) {
            if ((int) $rows->first()->measure_id === $measureId || abs((float) $rows->sum('quantity_delta')) < 0.0000005) {
                continue;
            }
            $label = Measure::find($rows->first()->measure_id)?->name ?? 'без единицы';
            throw ValidationException::withMessages([
                $key => "На складе есть остаток товара в единице «{$label}». Выберите эту единицу или сначала скорректируйте остатки в исходных документах.",
            ]);
        }
    }

    public static function rules(): array
    {
        return [
            'measure_id' => ['sometimes', 'required', 'integer', 'exists:measures,id'],
            'unit_weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0.000001', 'max:1000000000'],
            'existing_price_basis' => ['sometimes', 'in:selected_unit,kg'],
        ];
    }
}
