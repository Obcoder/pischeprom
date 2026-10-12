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
        $measurement = app(GoodMeasurement::class)->assertConfigured($good);
        app(GoodMeasurement::class)->assertSnapshot($good, $input['measurement'] ?? null);
        $vat = (float) $input['vat_rate'];
        $method = $input['method'];
        $cost = null;
        $source = null;
        if ($method === 'saved') {
            $calculation = GoodPriceCalculation::where('good_id', $good->id)->findOrFail($input['calculation_id'] ?? 0);
            if (! $measurement['kilograms_per_unit'] || (int) $calculation->currency_id !== (int) $input['currency_id']) {
                $this->invalid('calculation_id', 'Для применения расчёта нужны масса единицы товара и та же валюта.');
            }
            if ($calculation->sale_net_per_kg === null) {
                $this->invalid('calculation_id', 'В сохранённом расчёте нет цены без НДС.');
            }
            $net = $calculation->sale_net_per_kg * $measurement['kilograms_per_unit'];
            $cost = $calculation->purchase_net_per_kg !== null ? $calculation->purchase_net_per_kg * $measurement['kilograms_per_unit'] : null;
            $gross = $net * (1 + $vat / 100);
            $source = ['kind' => 'calculation', 'id' => $calculation->id, 'name' => $calculation->name];
        } elseif ($method === 'manual') {
            if (! isset($input['amount'])) {
                $this->invalid('amount', 'Укажите цену.');
            }
            $gross = $input['amount_includes_vat'] ? (float) $input['amount'] : (float) $input['amount'] * (1 + $vat / 100);
        } else {
            $source = $this->sources($good)->firstWhere('key', $input['source_key'] ?? '');
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
            $cost = (float) $source['price'] / (float) $source['denominator'] * $factor;
            if ($input['source_includes_vat']) {
                $cost /= 1 + (float) $input['source_vat_rate'] / 100;
            }
            $parameter = (float) ($input['parameter'] ?? 0);
            if ($method === 'margin' && $parameter >= 100) {
                $this->invalid('parameter', 'Маржа должна быть меньше 100%.');
            }
            $net = match ($method) {
                'markup' => $cost * (1 + $parameter / 100),
                'absolute' => $cost + $parameter,
                'margin' => $cost / (1 - $parameter / 100),
            };
            $gross = $net * (1 + $vat / 100);
        }
        $step = (float) ($input['rounding_step'] ?? 0);
        if ($step > 0) {
            $gross = ceil(round($gross / $step, 8)) * $step;
        }
        $gross = round($gross, 4);
        $net = round($gross / (1 + $vat / 100), 4);
        if (! is_finite($gross) || $gross <= 0 || $gross >= 1e12) {
            $this->invalid('amount', 'Итоговая цена должна быть больше нуля и меньше 1 трлн.');
        }
        $profit = $cost === null ? null : $net - $cost;

        return [
            'price_net' => $net, 'price_gross' => $gross, 'vat_rate' => $vat,
            'currency_id' => (int) $input['currency_id'], 'measurement' => $measurement,
            'cost_net' => $cost === null ? null : round($cost, 4),
            'profit' => $profit === null ? null : round($profit, 4),
            'markup_percent' => $cost > 0 ? round($profit / $cost * 100, 4) : null,
            'margin_percent' => $profit !== null && $net > 0 ? round($profit / $net * 100, 4) : null,
            'source' => $source,
        ];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
