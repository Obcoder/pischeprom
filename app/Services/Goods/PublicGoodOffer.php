<?php

namespace App\Services\Goods;

use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicGoodOffer
{
    /** Prices and quantities use the accounting unit configured on the product. */
    public function for(Good $good): array
    {
        return $this->offer($good, $this->pricesFor($good)->first());
    }

    /** Resolve a public listing without a price query for every card. */
    public function forMany(Collection $goods): Collection
    {
        $goods = $goods->keyBy('id');
        if ($goods->isEmpty()) {
            return collect();
        }

        $prices = $this->publicPrices()->whereIn('good_id', $goods->keys())->get()->groupBy('good_id');

        return $goods->map(fn (Good $good): array => $this->offer(
            $good,
            $this->preferredPrices($prices->get($good->id, collect()))->first(),
        ));
    }

    private function offer(Good $good, ?GoodPriceTypeValue $price): array
    {
        $value = $price ? (float) ($price->price_gross ?? $price->price_net) : null;
        $weight = $good->denominator > 0 ? (float) $good->denominator : null;
        $measurement = $good->measurement();

        return [
            'price' => $value,
            'price_id' => $price?->id,
            'includes_vat' => $price?->price_gross !== null,
            'price_unit' => $measurement['unit_label'] === 'кг' ? 'kg' : 'unit',
            'price_unit_label' => $measurement['unit_label'] ?? 'единица не задана',
            'measure_id' => $measurement['measure_id'],
            'measurement' => $measurement,
            'unit_weight_kg' => $measurement['kilograms_per_unit'],
            'can_order' => $measurement['measure_id'] !== null,
            'unit_price' => $value,
            // A physical pack quote is informational; ordering uses the accounting unit price.
            'package_price' => $value !== null && $weight !== null && $measurement['kilograms_per_unit'] !== null
                ? round($value * $weight / $measurement['kilograms_per_unit'], 4) : null,
            'currency_code' => $price?->currency?->code ?: $price?->priceType?->currency?->code ?: 'RUB',
            'package_weight' => $weight,
        ];
    }

    /** @return Collection<int, GoodPriceTypeValue> */
    public function pricesFor(Good $good): Collection
    {
        return $this->preferredPrices($this->publicPrices()->where('good_id', $good->id)->get());
    }

    private function publicPrices(): Builder
    {
        return GoodPriceTypeValue::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
            ->whereHas('priceType', fn ($query) => $query->where('is_active', true)->where('is_public', true))
            ->with(['priceType.currency', 'currency'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function preferredPrices(Collection $prices): Collection
    {
        return $prices
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
