<?php

namespace App\Services\Avito;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Avito has one integer price; its sale quantity is separate from Good accounting units. */
class AvitoPriceBasis
{
    public static function rules(): array
    {
        return [
            'price_basis' => ['sometimes', 'array', 'required_array_keys:mode'],
            'price_basis.mode' => ['required_with:price_basis', Rule::in(['unit', 'package', 'custom'])],
            'price_basis.quantity' => ['exclude_unless:price_basis.mode,custom', 'nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'price_basis.label' => ['nullable', 'string', 'max:80'],
            'price_basis.package_kg' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'price_basis.measurement' => ['sometimes', 'array'],
            'price_basis.measurement.measure_id' => ['nullable', 'integer', 'min:1'],
            'price_basis.measurement.unit_label' => ['nullable', 'string', 'max:80'],
            'price_basis.measurement.kilograms_per_unit' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
        ];
    }

    public function options(array $good): array
    {
        return [
            'unit' => $this->resolve($good, ['mode' => 'unit']),
            'package' => $this->resolve($good, ['mode' => 'package']),
            'custom' => ['mode' => 'custom', 'quantity' => null, 'label' => '', 'available' => ! empty($good['measurement']['measure_id'])],
        ];
    }

    public function resolve(array $good, ?array $settings = null): array
    {
        $settings ??= [];
        $mode = $settings['mode'] ?? 'unit';
        $measurement = $good['measurement'] ?? [];
        $unitLabel = trim((string) ($measurement['unit_label'] ?? ''));
        $quantity = 1.0;
        $label = $unitLabel;
        $packageKg = null;
        $error = empty($measurement['measure_id']) || $unitLabel === ''
            ? 'Укажите единицу учёта товара перед настройкой цены Avito.' : null;

        if ($mode === 'package') {
            $packageKg = $this->positive($good['denominator'] ?? null);
            $unitKg = $this->positive($measurement['kilograms_per_unit'] ?? null);
            // denominator is physical package weight in kg, not a count of
            // accounting units. A 10 kg box priced per box needs factor 1.
            $quantity = $packageKg && $unitKg ? $packageKg / $unitKg : null;
            $label = 'упаковка';
            if ($quantity === null || ! is_finite($quantity) || $quantity <= 0) {
                $error = 'Для цены за упаковку задайте массу упаковки и массу единицы учёта в кг в карточке товара.';
            }
        } elseif ($mode === 'custom') {
            $quantity = $this->positive($settings['quantity'] ?? null);
            $label = trim(strip_tags((string) ($settings['label'] ?? '')));
            if ($quantity === null || $quantity > 1000000000 || $label === '' || mb_strlen($label) > 80) {
                $error = 'Укажите положительное количество в единицах товара и название объёма продажи (например, коробка или минимальная партия).';
            }
        } elseif ($mode !== 'unit') {
            $error = 'Выберите единицу, упаковку или свой объём продажи.';
        }

        if (isset($settings['measurement']) && ! $this->sameMeasurement($measurement, (array) $settings['measurement'])) {
            $error = 'Единица учёта или её масса изменились. Подтвердите текущую единицу и заново проверьте объём для Avito.';
        }
        if ($mode === 'package' && isset($settings['package_kg'])
            && ($packageKg === null || abs((float) $settings['package_kg'] - $packageKg) > 0.0000001)) {
            $error = 'Масса упаковки изменилась. Подтвердите новый объём для Avito.';
        }

        return [
            'mode' => $mode,
            'quantity' => $quantity,
            'label' => $label,
            'measurement' => $measurement,
            'package_kg' => $packageKg,
            'available' => $error === null,
            'block_reason' => $error,
        ];
    }

    public function calculate(array $good, mixed $amount, ?array $settings = null, bool $finalOverride = false): array
    {
        $basis = $this->resolve($good, $settings);
        $error = $basis['block_reason'];
        $total = is_numeric($amount) && is_finite((float) $amount) && $basis['quantity'] !== null
            ? (float) $amount * ($finalOverride ? 1 : $basis['quantity']) : null;
        $value = null;
        if ($total === null || ! is_finite($total) || $total < 0 || round($total) > 999999999999) {
            $error ??= 'Итоговая цена должна быть числом от 0 до 999 999 999 999 RUB.';
        } else {
            $value = (int) round($total);
        }
        $snapshot = Arr::only($basis, ['mode', 'quantity', 'label', 'measurement', 'package_kg']);
        $formula = $total === null || ! is_finite($total) ? null : ($finalOverride
            ? 'Итоговая цена задана вручную: '.$this->number($amount).' RUB / '.$basis['label']
            : $this->number($amount).' RUB / '.($basis['measurement']['unit_label'] ?? 'ед.').' × '
                .$this->number($basis['quantity']).' '.($basis['measurement']['unit_label'] ?? 'ед.').' = '
                .$this->number($total).' RUB / '.$basis['label']);

        return [
            'price_basis' => $snapshot,
            'total_unrounded' => $total !== null && is_finite($total) ? $total : null,
            'avito_value' => $value,
            'sale_unit_label' => $basis['label'],
            'formula' => $formula,
            'can_apply' => $error === null,
            'block_reason' => $error,
            'is_final_override' => $finalOverride,
        ];
    }

    public function descriptionFact(array $good, ?array $settings = null): ?string
    {
        $basis = $this->resolve($good, $settings);
        if (! $basis['available']) {
            return null;
        }
        $quantity = $this->number($basis['quantity']).' '.$basis['measurement']['unit_label'];

        return $basis['mode'] === 'unit'
            ? 'Цена объявления за: '.$quantity
            : 'Цена объявления за: '.$basis['label'].' ('.$quantity.')';
    }

    private function sameMeasurement(array $current, array $snapshot): bool
    {
        $currentKg = $current['kilograms_per_unit'] ?? null;
        $snapshotKg = $snapshot['kilograms_per_unit'] ?? null;

        return (int) ($current['measure_id'] ?? 0) === (int) ($snapshot['measure_id'] ?? 0)
            && ($currentKg === null || $snapshotKg === null
                ? $currentKg === $snapshotKg
                : is_numeric($snapshotKg) && abs((float) $currentKg - (float) $snapshotKg) < 0.0000001);
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0 ? (float) $value : null;
    }

    private function number(mixed $value): string
    {
        if ((float) $value !== 0.0 && abs((float) $value) < 0.000001) {
            return str_replace('.', ',', sprintf('%.10g', (float) $value));
        }

        return rtrim(rtrim(number_format((float) $value, 6, ',', ' '), '0'), ',');
    }
}
