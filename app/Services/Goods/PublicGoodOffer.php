<?php

namespace App\Services\Goods;

use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicGoodOffer
{
    /** Prices in the public catalog are per kilogram when a package weight is known. */
    public function for(Good $good): array
    {
        $price = $this->pricesFor($good)->first();
        $value = $price ? (float) ($price->price_gross ?? $price->price_net) : null;
        $weight = $good->denominator > 0 ? (float) $good->denominator : null;

        return [
            'price' => $value,
            'price_id' => $price?->id,
            'includes_vat' => $price?->price_gross !== null,
            'price_unit' => $weight ? 'kg' : 'package',
            'price_unit_label' => $weight ? 'кг' : 'упаковка',
            'package_price' => $value !== null ? round($value * ($weight ?? 1), 4) : null,
            'currency_code' => $price?->currency?->code ?: $price?->priceType?->currency?->code ?: 'RUB',
            'package_weight' => $weight,
        ];
    }

    /** @return Collection<int, GoodPriceTypeValue> */
    public function pricesFor(Good $good): Collection
    {
        return $good->priceTypeValues()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
            ->whereHas('priceType', fn ($query) => $query->where('is_active', true)->where('is_public', true))
            ->with(['priceType.currency', 'currency'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->filter(function (GoodPriceTypeValue $price): bool {
                $text = Str::lower($price->priceType->code.' '.$price->priceType->name);

                return ! Str::contains($text, ['partner', 'партн', 'дилер', 'dealer', 'diler'])
                    && ($price->price_gross ?? $price->price_net ?? 0) > 0;
            })
            ->sortBy(fn (GoodPriceTypeValue $price) => [
                Str::contains(Str::lower($price->priceType->code.' '.$price->priceType->name), ['retail', 'rozn', 'рознич', 'розница']) ? 0 : 1,
                $price->priceType->sort_order ?? 100,
            ])
            ->values();
    }
}
