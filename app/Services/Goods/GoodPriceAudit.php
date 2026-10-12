<?php

namespace App\Services\Goods;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceHistory;
use App\Models\PriceType;
use Illuminate\Database\Eloquent\Model;

class GoodPriceAudit
{
    public function record(Model $price, string $event): void
    {
        $private = $price instanceof GoodEntityPrice;
        $good = Good::find($price->good_id);
        $target = $private ? Entity::find($price->entity_id) : PriceType::find($price->price_type_id);
        $metadata = [
            'good_name' => $good?->name,
            'target_name' => $target?->name,
            'measurement' => $good?->measurement(),
        ];
        $snapshot = function (array $attributes, ?array $measurement) use ($metadata, $target, $private): array {
            foreach (['pricing_input', 'pricing_result'] as $key) {
                if (is_string($attributes[$key] ?? null)) {
                    $attributes[$key] = json_decode($attributes[$key], true);
                }
            }
            $currencyId = $attributes['currency_id'] ?? ($private ? null : $target?->currency_id);

            return [...$attributes, ...$metadata,
                'currency_code' => $currencyId ? Currency::find($currencyId)?->code : null,
                'measurement' => $measurement ?? $metadata['measurement']];
        };
        GoodPriceHistory::create([
            'good_id' => $price->good_id, 'scope' => $private ? 'entity' : 'common',
            'price_record_id' => $price->id, 'event' => $event,
            'actor_id' => auth()->id(), 'actor_name' => auth()->user()?->name,
            'before' => $event === 'created' ? null : $snapshot($price->getRawOriginal(), $price->auditMeasurementBefore),
            'after' => $event === 'deleted' ? null : $snapshot($price->fresh()->getAttributes(), $price->auditMeasurementAfter),
            'created_at' => now(),
        ]);
    }
}
