<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->safeDate(),
            'amount' => $this->attribute('amount'),
            'items_count' => $this->goods_count ?? ($this->relationLoaded('goods') ? $this->goods->count() : 0),

            'entity' => $this->whenLoaded('entity', function ($entity) {
                return [
                    'id' => $this->attributeFrom($entity, 'id'),
                    'name' => $this->attributeFrom($entity, 'name'),
                    'full_name' => $this->attributeFrom($entity, 'full_name'),
                    'INN' => $this->attributeFrom($entity, 'INN'),
                    'KPP' => $this->attributeFrom($entity, 'KPP'),
                    'OGRN' => $this->attributeFrom($entity, 'OGRN'),
                    'units' => $entity->relationLoaded('units')
                        ? $entity->units->map(fn ($unit) => [
                            'id' => $this->attributeFrom($unit, 'id'),
                            'name' => $this->attributeFrom($unit, 'name'),
                            'is_customer' => (bool) $this->attributeFrom($unit, 'is_customer'),
                            'is_supplier' => (bool) $this->attributeFrom($unit, 'is_supplier'),
                        ])->values()
                        : [],
                ];
            }),

            'items' => $this->whenLoaded('goods', function () {
                return $this->goods->map(function ($good) {
                    $thumb = $this->attributeFrom($good, 'ava_thumb') ?: $this->attributeFrom($good, 'ava_image');

                    return [
                        'pivot_id' => $this->attributeFrom($good->pivot, 'id'),
                        'good_id' => $this->attributeFrom($good, 'id'),
                        'good_name' => $this->attributeFrom($good, 'name'),
                        'good' => [
                            'id' => $this->attributeFrom($good, 'id'),
                            'name' => $this->attributeFrom($good, 'name'),
                            'slug' => $this->attributeFrom($good, 'slug'),
                            'ava_image' => $this->attributeFrom($good, 'ava_image'),
                            'ava_thumb' => $this->attributeFrom($good, 'ava_thumb'),
                            'thumbnail_url' => $thumb,
                        ],
                        'quantity' => (float) ($this->attributeFrom($good->pivot, 'quantity') ?? 0),
                        'measure_id' => $this->attributeFrom($good->pivot, 'measure_id'),
                        'price' => (float) ($this->attributeFrom($good->pivot, 'price') ?? 0),
                        'currency_id' => $this->attributeFrom($good->pivot, 'currency_id'),
                        'total' => (float) ($this->attributeFrom($good->pivot, 'total') ?? 0),
                    ];
                })->values();
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function safeDate(): ?string
    {
        $attributes = method_exists($this->resource, 'getAttributes')
            ? $this->resource->getAttributes()
            : [];
        $value = $attributes['date'] ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! $value || $value === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function attribute(string $key): mixed
    {
        return $this->attributeFrom($this->resource, $key);
    }

    private function attributeFrom($model, string $key): mixed
    {
        if (! $model || ! method_exists($model, 'getAttributes')) {
            return null;
        }

        $attributes = $model->getAttributes();

        if (! array_key_exists($key, $attributes)) {
            return null;
        }

        return $model->getAttribute($key);
    }
}
