<?php

namespace App\Services\Goods;

use App\Models\Good;
use App\Models\GoodPriceCalculation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodPricingCalculator
{
    public function sources(Good $good): Collection
    {
        $quotations = DB::table('quotations as q')->where('q.good_id', $good->id)
            ->leftJoin('units as u', 'u.id', '=', 'q.unit_id')
            ->leftJoin('measures as m', 'm.id', '=', 'q.measure_id')
            ->leftJoin('currencies as c', 'c.id', '=', 'q.currency_id')
            ->orderByDesc('q.id')->get(['q.id', 'q.price', 'q.denominator', 'q.measure_id', 'q.currency_id', 'q.created_at as date', 'u.name as supplier', 'm.name as unit_label', 'c.code as currency_code'])
            ->map(fn ($row) => [...(array) $row, 'key' => 'quotation:'.$row->id, 'kind' => 'quotation']);
        $purchases = DB::table('good_purchase as gp')->where('gp.good_id', $good->id)
            ->join('purchases as p', 'p.id', '=', 'gp.purchase_id')
            ->leftJoin('entities as e', 'e.id', '=', 'p.entity_id')
            ->leftJoin('measures as m', 'm.id', '=', 'gp.measure_id')
            ->leftJoin('currencies as c', 'c.id', '=', 'gp.currency_id')
            ->orderByDesc('p.date')->orderByDesc('gp.id')
            ->get(['gp.id', 'gp.purchase_id', 'gp.price', 'gp.measure_id', 'gp.currency_id', 'p.date', 'e.name as supplier', 'm.name as unit_label', 'c.code as currency_code'])
            ->map(fn ($row) => [...(array) $row, 'denominator' => 1, 'key' => 'purchase:'.$row->id, 'kind' => 'purchase']);

        return $purchases->concat($quotations)->values();
    }

    public function calculate(Good $good, array $input): array
    {
        $units = app(GoodMeasurement::class);
        $measurement = $units->assertConfigured($good);
        $units->assertSnapshot($good, $input['measurement'] ?? null);
        $vat = (float) $input['vat_rate'];
        $method = $input['method'];
        $cost = $purchaseNet = $sourceGross = $sourceVat = null;
        $extraCost = 0.0;
        $source = null;
        $breakdown = [];
        $add = function (string $key, string $kind, string $label, float $amount, float $subtotal, string $formula, array $details = []) use (&$breakdown): void {
            $breakdown[] = [...$details, 'key' => $key, 'kind' => $kind, 'label' => $label,
                'amount' => round($amount, 4), 'subtotal' => round($subtotal, 4), 'formula' => $formula];
        };
        $format = fn ($number) => rtrim(rtrim(number_format((float) $number, 4, ',', ' '), '0'), ',');

        if ($method === 'saved') {
            [$net, $cost, $source] = $this->saved($good, $input, $measurement);
            $add('saved', 'saved', 'Сохранённая цена без НДС', $net, $net, $source['name'] ?: 'Расчёт №'.$source['id']);
        } elseif ($method === 'manual') {
            if (! isset($input['amount'])) {
                $this->invalid('amount', 'Укажите цену.');
            }
            $amount = (float) $input['amount'];
            $net = $input['amount_includes_vat'] ? $amount / (1 + $vat / 100) : $amount;
            $add('manual', 'manual', 'Цена без НДС', $net, $net, $input['amount_includes_vat']
                ? $format($amount).' ÷ (1 + '.$format($vat).'%): выделение НДС из введённой цены'
                : 'Введена вручную: '.$format($amount));
        } else {
            [$source, $sourceGross] = $this->purchase($good, $input, $measurement);
            $purchaseNet = $input['source_includes_vat']
                ? $sourceGross / (1 + (float) $input['source_vat_rate'] / 100) : $sourceGross;
            $sourceVat = $sourceGross - $purchaseNet;
            $add('purchase', 'purchase', 'Закупочная цена за единицу', $sourceGross, $sourceGross,
                $format($source['price']).' ÷ '.$format($source['denominator']).' × '.$format($source['conversion_factor']),
                ['input_amount' => (float) $source['price'], 'source_unit' => $source['unit_label'], 'source_denominator' => (float) $source['denominator'], 'conversion_factor' => $source['conversion_factor']]);
            $add('purchase_vat', 'vat_extract', 'Выделение входящего НДС', -$sourceVat, $purchaseNet,
                $input['source_includes_vat'] ? $format($sourceGross).' − '.$format($sourceGross).' ÷ (1 + '.$format($input['source_vat_rate']).'%)' : 'Источник без НДС: вычет не применяется',
                ['vat_rate' => (float) $input['source_vat_rate'], 'enabled' => (bool) $input['source_includes_vat']]);
            $cost = $purchaseNet;
            foreach ($input['elements'] ?? [] as $index => $element) {
                $enabled = (bool) ($element['enabled'] ?? true);
                $value = (float) $element['value'];
                $fixed = $element['kind'] === 'fixed';
                if ($enabled && $fixed) {
                    if ((int) ($element['currency_id'] ?? 0) !== (int) $input['currency_id']) {
                        $this->invalid("elements.{$index}.currency_id", 'Валюта элемента «'.$element['name'].'» должна совпадать с валютой цены.');
                    }
                    $units->assertSnapshot($good, $element['measurement'] ?? null, "elements.{$index}.measurement");
                    if (empty($element['measurement'])) {
                        $this->invalid("elements.{$index}.measurement", 'Для суммы элемента нужна единица товара.');
                    }
                }
                $base = ($element['percentage_base'] ?? 'purchase_net') === 'running_cost' ? $cost : $purchaseNet;
                $elementNet = $fixed ? ($element['includes_vat'] ? $value / (1 + (float) $element['vat_rate'] / 100) : $value) : $base * $value / 100;
                $delta = $enabled ? $elementNet : 0;
                $cost += $delta;
                $formula = ! $enabled ? 'Элемент отключён: 0' : ($fixed
                    ? ($element['includes_vat'] ? $format($value).' ÷ (1 + '.$format($element['vat_rate']).'%)' : $format($value).' без НДС')
                    : $format($base).' × '.$format($value).'% ('.(($element['percentage_base'] ?? '') === 'running_cost' ? 'накопленная себестоимость' : 'закупка без НДС').')');
                $add('element:'.$index, 'extra', $element['name'], $delta, $cost, $formula,
                    ['element_id' => $element['element_id'] ?? null, 'element_revision' => $element['element_revision'] ?? null,
                        'enabled' => $enabled, 'input_amount' => $value, 'basis' => $fixed ? null : round($base, 4),
                        'vat_amount' => $enabled && $fixed ? round($value - $elementNet, 4) : 0,
                        'vat_rate' => $fixed ? (float) $element['vat_rate'] : null]);
            }
            $extraCost = $cost - $purchaseNet;
            $parameter = (float) ($input['parameter'] ?? 0);
            if ($method === 'margin' && $parameter >= 100) {
                $this->invalid('parameter', 'Маржа должна быть меньше 100%.');
            }
            $net = match ($method) {
                'markup' => $cost * (1 + $parameter / 100),
                'absolute' => $cost + $parameter,
                'margin' => $cost / (1 - $parameter / 100),
            };
            $formula = match ($method) {
                'markup' => $format($cost).' × '.$format($parameter).'% от полной себестоимости',
                'absolute' => '+ '.$format($parameter).' на единицу товара',
                'margin' => $format($cost).' ÷ (1 − '.$format($parameter).'%) − '.$format($cost),
            };
            $add('profit', 'profit', match ($method) {
                'margin' => 'Прибыль при целевой марже', 'markup' => 'Наценка', default => 'Надбавка'
            }, $net - $cost, $net, $formula,
                ['parameter' => $parameter, 'method' => $method, 'basis' => round($cost, 4)]);
        }
        $profitBeforeRounding = $cost === null ? null : $net - $cost;
        $vatBeforeRounding = $net * $vat / 100;
        $grossBeforeRounding = $net + $vatBeforeRounding;
        $add('sale_vat', 'vat_add', 'Исходящий НДС', $vatBeforeRounding, $grossBeforeRounding, $format($net).' × '.$format($vat).'%', ['vat_rate' => $vat]);
        $step = (float) ($input['rounding_step'] ?? 0);
        if (! is_finite($grossBeforeRounding) || $grossBeforeRounding <= 0 || $grossBeforeRounding >= 1e12) {
            $this->invalid('amount', 'Итоговая цена должна быть больше нуля и меньше 1 трлн.');
        }
        // Round in four-decimal money units, not in a ratio whose precision depends on step size.
        $grossUnits = (int) round($grossBeforeRounding * 10000);
        $stepUnits = (int) round($step * 10000);
        $gross = $stepUnits > 0 ? intdiv($grossUnits + $stepUnits - 1, $stepUnits) * $stepUnits / 10000 : $grossUnits / 10000;
        $gross = round($gross, 4);
        $net = round($gross / (1 + $vat / 100), 4);
        if (! is_finite($gross) || $gross <= 0 || $gross >= 1e12) {
            $this->invalid('amount', 'Итоговая цена должна быть больше нуля и меньше 1 трлн.');
        }
        // Reconcile four-decimal displayed contributions so the chain sums exactly.
        $displayed = array_sum(array_column($breakdown, 'amount'));
        $rounding = round($gross - $grossBeforeRounding, 4);
        $add('rounding', 'rounding', 'Округление', $gross - $displayed, $gross,
            $step > 0 ? 'Вверх до кратного '.$format($step) : 'Точность хранения: 4 знака');
        $profit = $cost === null ? null : $net - $cost;

        return [
            'price_net' => $net, 'price_gross' => $gross, 'vat_rate' => $vat,
            'currency_id' => (int) $input['currency_id'], 'measurement' => $measurement,
            'cost_net' => $cost === null ? null : round($cost, 4),
            'purchase_net' => $purchaseNet === null ? null : round($purchaseNet, 4),
            'source_gross' => $sourceGross === null ? null : round($sourceGross, 4),
            'source_vat' => $sourceVat === null ? null : round($sourceVat, 4),
            'extra_cost_net' => round($extraCost, 4), 'vat_amount' => round($gross - $net, 4),
            'profit_before_rounding' => $profitBeforeRounding === null ? null : round($profitBeforeRounding, 4),
            'rounding_gross' => $rounding, 'breakdown' => $breakdown,
            'profit' => $profit === null ? null : round($profit, 4),
            'markup_percent' => $cost > 0 ? round($profit / $cost * 100, 4) : null,
            'margin_percent' => $profit !== null && $net > 0 ? round($profit / $net * 100, 4) : null,
            'source' => $source,
        ];
    }

    private function purchase(Good $good, array $input, array $measurement): array
    {
        if (($input['source_key'] ?? '') === 'manual') {
            if (! isset($input['purchase_amount']) || $input['purchase_amount'] < 0) {
                $this->invalid('purchase_amount', 'Укажите закупочную цену за единицу товара.');
            }
            $source = ['kind' => 'manual', 'id' => null, 'price' => (float) $input['purchase_amount'],
                'denominator' => 1, 'currency_id' => (int) $input['currency_id'], 'measure_id' => $measurement['measure_id'],
                'unit_label' => $measurement['unit_label'], 'supplier' => 'Ручной ввод'];
        } else {
            $source = $this->sources($good)->firstWhere('key', $input['source_key'] ?? '');
        }
        if (! $source) {
            $this->invalid('source_key', 'Выберите закупку или предложение именно этого товара.');
        }
        if (! $source['currency_id'] || (int) $source['currency_id'] !== (int) $input['currency_id']) {
            $this->invalid('currency_id', 'Валюта цены должна совпадать с валютой источника. Автоматическая конвертация не выполняется.');
        }
        if ($source['price'] === null || (float) $source['price'] < 0 || (float) $source['denominator'] <= 0 || ! $source['measure_id']) {
            $this->invalid('source_key', 'У источника должны быть заданы цена, единица и положительная фасовка.');
        }
        $factor = 1;
        if ((int) $source['measure_id'] !== (int) $measurement['measure_id']) {
            $sourceKg = app(GoodMeasurement::class)->kilograms($source['unit_label']);
            if (! $sourceKg || ! $measurement['kilograms_per_unit']) {
                $this->invalid('source_key', 'Единицы источника и товара несопоставимы. Укажите массу единицы или выберите другой источник.');
            }
            $factor = $measurement['kilograms_per_unit'] / $sourceKg;
        }
        $source['conversion_factor'] = $factor;

        return [$source, (float) $source['price'] / (float) $source['denominator'] * $factor];
    }

    private function saved(Good $good, array $input, array $measurement): array
    {
        if (! empty($input['saved_calculation_id'])) {
            $calculation = \App\Models\GoodPricingCalculation::where('good_id', $good->id)->findOrFail($input['saved_calculation_id']);
            if ($calculation->scope === 'entity' && (($input['scope'] ?? '') !== 'entity'
                || (int) $calculation->entity_id !== (int) ($input['target_id'] ?? 0)
                || (int) ($calculation->input['target_id'] ?? 0) !== (int) ($input['target_id'] ?? 0))) {
                $this->invalid('saved_calculation_id', 'Индивидуальный расчёт можно назначить только исходному Entity.');
            }
            if ((int) $calculation->currency_id !== (int) $input['currency_id']) {
                $this->invalid('currency_id', 'Валюта должна совпадать с валютой сохранённого расчёта.');
            }
            $old = $calculation->measurement;
            $same = (int) ($old['measure_id'] ?? 0) === (int) $measurement['measure_id']
                && ($old['kilograms_per_unit'] ?? null) == $measurement['kilograms_per_unit'];
            $factor = 1;
            if (! $same) {
                if (empty($old['kilograms_per_unit']) || ! $measurement['kilograms_per_unit']) {
                    $this->invalid('saved_calculation_id', 'Единицу сохранённого расчёта нельзя перевести в текущую единицу товара.');
                }
                $factor = $measurement['kilograms_per_unit'] / $old['kilograms_per_unit'];
            }
            $net = $calculation->result['price_net'] ?? null;
            if ($net === null) {
                $this->invalid('saved_calculation_id', 'В сохранённом расчёте нет цены без НДС.');
            }
            $cost = $calculation->result['cost_net'] ?? null;
            // A frozen gross price must survive reapplication without four-decimal drift.
            if (isset($calculation->result['price_gross'], $calculation->result['vat_rate'])
                && abs((float) $calculation->result['vat_rate'] - (float) $input['vat_rate']) < 0.0000001) {
                $net = (float) $calculation->result['price_gross'] / (1 + (float) $input['vat_rate'] / 100);
            }

            return [$net * $factor, $cost === null ? null : $cost * $factor,
                ['kind' => 'workspace_calculation', 'id' => $calculation->id, 'name' => $calculation->name, 'conversion_factor' => $factor]];
        }
        $calculation = GoodPriceCalculation::where('good_id', $good->id)->findOrFail($input['calculation_id'] ?? 0);
        if (! $measurement['kilograms_per_unit'] || (int) $calculation->currency_id !== (int) $input['currency_id']) {
            $this->invalid('calculation_id', 'Для применения расчёта нужны масса единицы товара и та же валюта.');
        }
        if ($calculation->sale_net_per_kg === null) {
            $this->invalid('calculation_id', 'В сохранённом расчёте нет цены без НДС.');
        }
        $net = $calculation->sale_net_per_kg * $measurement['kilograms_per_unit'];
        $cost = $calculation->purchase_net_per_kg !== null ? $calculation->purchase_net_per_kg * $measurement['kilograms_per_unit'] : null;

        return [$net, $cost, ['kind' => 'calculation', 'id' => $calculation->id, 'name' => $calculation->name]];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
