<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Internal catalog pricing; never attach these values to public catalog nodes. */
class CatalogGoodPricing
{
    public function forGoods(array $goodIds): Collection
    {
        $goodIds = array_values(array_unique(array_filter($goodIds)));
        if ($goodIds === []) {
            return collect();
        }

        $rankedPurchases = DB::table('good_purchase as line')
            ->join('purchases as purchase', 'purchase.id', '=', 'line.purchase_id')
            ->leftJoin('currencies as currency', 'currency.id', '=', 'line.currency_id')
            ->leftJoin('measures as measure', 'measure.id', '=', 'line.measure_id')
            ->whereIn('line.good_id', $goodIds)
            ->whereDate('purchase.date', '<=', today())
            ->select([
                'line.good_id', 'line.price', 'line.currency_id', 'line.measure_id',
                'purchase.id as purchase_id', 'purchase.date',
                'currency.code as currency_code', 'currency.name as currency_name',
                'measure.name as unit_label',
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY line.good_id ORDER BY purchase.date DESC, purchase.id DESC, line.id DESC) as position');
        $purchases = DB::query()->fromSub($rankedPurchases, 'latest_purchase')
            ->where('position', 1)->get()->keyBy('good_id');

        // Publication controls the public website, not whether an internal price is current.
        $prices = DB::table('good_price_type_values as value')
            ->join('price_types as type', 'type.id', '=', 'value.price_type_id')
            ->leftJoin('currencies as currency', 'currency.id', '=', DB::raw('COALESCE(value.currency_id, type.currency_id)'))
            ->whereIn('value.good_id', $goodIds)
            ->where('type.is_active', true)
            ->where(fn ($query) => $query->whereNull('value.valid_from')->orWhereDate('value.valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('value.valid_to')->orWhereDate('value.valid_to', '>=', today()))
            ->orderBy('type.sort_order')->orderBy('type.name')->orderBy('value.id')
            ->get([
                'value.id', 'value.good_id', 'value.price_gross', 'value.price_net', 'value.vat_rate',
                'type.name as name', 'type.code as code',
                'currency.id as currency_id', 'currency.code as currency_code', 'currency.name as currency_name',
            ])->groupBy('good_id');

        return collect($goodIds)->mapWithKeys(function ($goodId) use ($purchases, $prices): array {
            $purchase = $purchases->get($goodId);
            $purchasePrice = $purchase && $purchase->price !== null ? (float) $purchase->price : null;
            $kgFactor = $this->kilograms($purchase?->unit_label);
            $purchasePerKg = $purchasePrice !== null && $kgFactor !== null ? $purchasePrice / $kgFactor : null;

            return [$goodId => [
                'purchase' => $purchase ? [
                    'purchase_id' => $purchase->purchase_id,
                    'date' => $purchase->date,
                    'price' => $purchasePrice,
                    'currency_label' => $purchase->currency_code ?: $purchase->currency_name,
                    'unit_label' => $purchase->unit_label,
                ] : null,
                'sales' => ($prices->get($goodId) ?? collect())->map(function ($price) use ($purchase, $purchasePerKg): array {
                    $amount = $price->price_gross ?? $price->price_net;
                    $amount = $amount === null ? null : (float) $amount;
                    $includesVat = $price->price_gross !== null;
                    if (! $includesVat && $amount !== null && $price->vat_rate !== null) {
                        $amount = round($amount * (1 + (float) $price->vat_rate / 100), 4);
                        $includesVat = true;
                    }
                    $reason = match (true) {
                        ! $purchase => 'Нет закупок',
                        $purchasePerKg === null => 'Единица закупки не сопоставима с кг',
                        $purchasePerKg <= 0 => 'Закупочная цена должна быть больше нуля',
                        ! $purchase->currency_id || ! $price->currency_id => 'Не указана валюта',
                        (int) $purchase->currency_id !== (int) $price->currency_id => 'Разные валюты закупки и продажи',
                        $amount === null => 'Цена продажи не задана',
                        ! $includesVat => 'Не указана ставка НДС для сопоставления цен',
                        default => null,
                    };

                    return [
                        'id' => $price->id,
                        'name' => $price->name,
                        'code' => $price->code,
                        'price' => $amount,
                        'currency_label' => $price->currency_code ?: $price->currency_name,
                        // The internal price editor and calculations store values per kilogram.
                        'unit_label' => 'кг',
                        'includes_vat' => $includesVat,
                        'markup_percent' => $reason === null ? round(($amount / $purchasePerKg - 1) * 100, 2) : null,
                        'markup_unavailable_reason' => $reason,
                    ];
                })->values()->all(),
            ]];
        });
    }

    private function kilograms(?string $unit): ?float
    {
        return match (mb_strtolower(trim((string) $unit))) {
            'кг', 'килограмм', 'килограммы', 'kg', 'kilogram', 'kilograms' => 1,
            'т', 'тн', 'тонна', 'тонны', 'тонн', 'ton', 'tons', 'tonne', 'tonnes' => 1000,
            'г', 'гр', 'грамм', 'граммы', 'gram', 'grams', 'g' => 0.001,
            default => null,
        };
    }
}
