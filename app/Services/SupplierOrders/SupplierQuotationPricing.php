<?php

namespace App\Services\SupplierOrders;

use App\Models\Quotation;
use App\Services\Goods\GoodMeasurement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** One interpretation of a supplier offer for the editor, draft and letter. */
class SupplierQuotationPricing
{
    public function resolve(Quotation $quote, array $measurement): array
    {
        $details = ['id' => $quote->id, 'price' => $quote->price, 'denominator' => $quote->denominator,
            'unit_label' => $quote->measure?->name, 'quoted_at' => $quote->quoted_at?->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(), 'vat_rate' => $quote->vat_rate,
            'price_includes_vat' => $quote->price_includes_vat, 'minimum_quantity' => $quote->minimum_quantity,
            'lead_time_days' => $quote->lead_time_days];
        $result = ['unit_price' => null, 'currency_id' => null, 'currency_code' => null, 'quotation_id' => null,
            'quotation' => $details, 'quotation_warning' => null, 'quotation_snapshot' => null];
        $warning = null;
        if ($quote->quoted_at?->isAfter(today())) {
            $warning = 'Предложение ещё не действует. Уточните цену вручную.';
        } elseif ($quote->valid_until?->isBefore(today())) {
            $warning = 'Срок предложения истёк. Уточните цену вручную.';
        } elseif ($quote->price === null || ! is_numeric($quote->price) || (float) $quote->price < 0
            || $quote->denominator <= 0 || ! $quote->currency?->code || ! $quote->measure_id || ! $measurement['measure_id']) {
            $warning = 'В предложении не заполнены цена, валюта или единица цены. Дополните предложение в прайс-листе.';
        }
        $factor = 1;
        if (! $warning && (int) $quote->measure_id !== (int) $measurement['measure_id']) {
            $kg = app(GoodMeasurement::class)->kilograms($quote->measure?->name);
            if (! $kg || ! $measurement['kilograms_per_unit']) {
                $warning = 'Единицу предложения нельзя сопоставить с единицей товара. Уточните цену вручную.';
            } else {
                $factor = $measurement['kilograms_per_unit'] / $kg;
            }
        }
        if ($warning) {
            return [...$result, 'quotation_warning' => $warning];
        }
        $price = BigDecimal::of($quote->price)->multipliedBy(number_format($factor, 9, '.', ''))
            ->dividedBy((string) $quote->denominator, 12, RoundingMode::HalfUp);
        $vatKnown = $quote->price_includes_vat !== null && $quote->vat_rate !== null;
        if ($vatKnown && ! $quote->price_includes_vat) {
            $price = $price->multipliedBy(BigDecimal::one()->plus(BigDecimal::of($quote->vat_rate)->dividedBy(100)));
        }
        $price = $price->toScale(4, RoundingMode::HalfUp);
        if ($price->isGreaterThanOrEqualTo('1000000000000')) {
            return [...$result, 'quotation_warning' => 'Цена за единицу товара превышает допустимую величину.'];
        }
        $vatLabel = ! $vatKnown ? 'НДС не указан' : ((float) $quote->vat_rate === 0.0 ? 'НДС 0%' : 'Включая НДС '.rtrim(rtrim((string) $quote->vat_rate, '0'), '.').'%');
        $snapshot = [...$details, 'unit_id' => $quote->unit_id, 'measure_id' => $quote->measure_id,
            'currency_id' => $quote->currency_id, 'currency_code' => $quote->currency->code,
            'conversion_factor' => $factor, 'normalized_price_includes_vat' => $vatKnown ? true : null, 'vat_label' => $vatLabel];

        return [...$result, 'unit_price' => $price->toFloat(), 'currency_id' => $quote->currency_id,
            'currency_code' => $quote->currency->code, 'quotation_id' => $quote->id, 'quotation_snapshot' => $snapshot];
    }
}
