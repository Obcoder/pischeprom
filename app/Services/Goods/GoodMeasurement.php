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
                $prices = $locked->priceTypeValues()->lockForUpdate()->get()
                    ->concat(\App\Models\GoodEntityPrice::where('good_id', $locked->id)->lockForUpdate()->get());
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
                    $price->auditMeasurementBefore = $before;
                    $price->auditMeasurementAfter = $after;
                    $factor = $toKg / $fromKg;
                    foreach (['price_net', 'price_gross'] as $field) {
                        if ($price->{$field} !== null) {
                            $price->{$field} = $this->scaleMoney($price->{$field}, $factor, $prefix);
                        }
                    }
                    if ($price->pricing_input) {
                        $input = $price->pricing_input;
                        $fields = ['rounding_step'];
                        if (($input['method'] ?? null) === 'manual') {
                            $fields[] = 'amount';
                        } elseif (($input['method'] ?? null) === 'absolute') {
                            $fields[] = 'parameter';
                        }
                        if (($input['source_key'] ?? null) === 'manual') {
                            $fields[] = 'purchase_amount';
                        }
                        foreach ($fields as $field) {
                            if (isset($input[$field])) {
                                $input[$field] = $this->scaleMoney($input[$field], $factor, $prefix);
                            }
                        }
                        foreach ($input['elements'] ?? [] as $index => $element) {
                            if (($element['kind'] ?? null) !== 'fixed') {
                                continue;
                            }
                            // Disabled templates may still have a different unit. Keep
                            // those snapshots intact so enabling them cannot silently
                            // reinterpret an amount in the current accounting unit.
                            $elementUnit = $element['measurement'] ?? $before;
                            if ((int) ($elementUnit['measure_id'] ?? 0) !== (int) ($before['measure_id'] ?? 0)
                                || ($elementUnit['kilograms_per_unit'] ?? null) != ($before['kilograms_per_unit'] ?? null)) {
                                continue;
                            }
                            $input['elements'][$index]['value'] = $this->scaleMoney($element['value'], $factor, $prefix);
                            $input['elements'][$index]['measurement'] = $after;
                        }
                        $input['measurement'] = $after;
                        $price->pricing_input = $input;
                    }
                    if ($price->pricing_result) {
                        $result = $price->pricing_result;
                        foreach (['price_net', 'price_gross', 'cost_net', 'profit', 'purchase_net', 'source_gross', 'source_vat',
                            'extra_cost_net', 'profit_before_rounding', 'vat_amount', 'rounding_gross'] as $field) {
                            if (isset($result[$field])) {
                                $result[$field] = $this->scaleMoney($result[$field], $factor, $prefix);
                            }
                        }
                        $result = $this->rescaleBreakdown($result, $factor, $before, $after, $prefix, $price->pricing_input ?? []);
                        $result['measurement'] = $after;
                        $price->pricing_result = $result;
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

    private function scaleMoney(float|string $amount, float $factor, string $prefix): float
    {
        $value = round((float) $amount * $factor, 4);
        if (! is_finite($value) || abs($value) >= 1e12) {
            throw ValidationException::withMessages([$prefix.'measure_id' => 'Цена после пересчёта выходит за допустимые пределы.']);
        }

        return $value;
    }

    private function rescaleBreakdown(array $result, float $factor, array $before, array $after, string $prefix, array $input): array
    {
        $manualSource = ($result['source']['kind'] ?? null) === 'manual';
        if (isset($result['source']['conversion_factor'])) {
            $result['source']['conversion_factor'] = $manualSource ? 1 : $result['source']['conversion_factor'] * $factor;
        }
        if ($manualSource) {
            $result['source']['price'] = $this->scaleMoney($result['source']['price'], $factor, $prefix);
            $result['source']['measure_id'] = $after['measure_id'];
            $result['source']['unit_label'] = $after['unit_label'];
        }
        if (empty($result['breakdown'])) {
            return $result;
        }
        $format = fn ($amount) => rtrim(rtrim(number_format((float) $amount, 4, ',', ' '), '0'), ',');
        $factorLabel = rtrim(rtrim(number_format($factor, 15, ',', ' '), '0'), ',');
        $roundingIndex = null;
        foreach ($result['breakdown'] as $index => &$stage) {
            $amount = $stage['amount'];
            foreach (['amount', 'subtotal', 'basis', 'vat_amount'] as $field) {
                if (isset($stage[$field])) {
                    $stage[$field] = $this->scaleMoney($stage[$field], $factor, $prefix);
                }
            }
            // input_amount for percentages and supplier offers is dimensionless
            // or expressed in the original source unit, respectively.
            $fixedElement = ($stage['kind'] ?? null) === 'extra' && ($stage['basis'] ?? null) === null;
            if (isset($stage['input_amount']) && ($fixedElement || ($manualSource && ($stage['key'] ?? null) === 'purchase'))) {
                $element = $fixedElement && preg_match('/^element:(\d+)$/', $stage['key'] ?? '', $matches)
                    ? ($input['elements'][(int) $matches[1]] ?? null) : null;
                $stage['input_amount'] = $element ? $element['value'] : $this->scaleMoney($stage['input_amount'], $factor, $prefix);
            }
            if (isset($stage['conversion_factor'])) {
                $stage['conversion_factor'] = $manualSource ? 1 : $stage['conversion_factor'] * $factor;
            }
            if ($manualSource && ($stage['key'] ?? null) === 'purchase') {
                $stage['source_unit'] = $after['unit_label'];
            }
            if (($stage['kind'] ?? null) === 'profit' && ($stage['method'] ?? null) === 'absolute') {
                $stage['parameter'] = $this->scaleMoney($stage['parameter'], $factor, $prefix);
            }
            $stage['formula'] = 'Пересчёт '.($before['unit_label'] ?: 'кг').' → '.$after['unit_label'].': '
                .$format($amount).' × '.$factorLabel.' = '.$format($stage['amount']);
            if (($stage['kind'] ?? null) === 'rounding') {
                $roundingIndex = $index;
            }
        }
        unset($stage);
        $total = round(array_sum(array_column($result['breakdown'], 'amount')), 4);
        $difference = round($result['price_gross'] - $total, 4);
        if ($roundingIndex === null && $difference != 0) {
            $roundingIndex = count($result['breakdown']);
            $result['breakdown'][] = ['key' => 'rounding', 'kind' => 'rounding', 'label' => 'Округление', 'amount' => 0];
        }
        if ($roundingIndex !== null && $difference != 0) {
            $result['breakdown'][$roundingIndex]['amount'] = round($result['breakdown'][$roundingIndex]['amount'] + $difference, 4);
            $result['breakdown'][$roundingIndex]['formula'] = 'После пересчёта: '.$format($result['price_gross']).' − '
                .$format(round($total - ($result['breakdown'][$roundingIndex]['amount'] - $difference), 4))
                .' = '.$format($result['breakdown'][$roundingIndex]['amount']).' (согласование точности 4 знака)';
        }
        $subtotal = 0;
        foreach ($result['breakdown'] as &$stage) {
            $subtotal = round($subtotal + $stage['amount'], 4);
            $stage['subtotal'] = $subtotal;
        }
        unset($stage);

        return $result;
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
