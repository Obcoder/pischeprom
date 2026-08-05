<?php

namespace App\Services\Orders;

use App\Models\Good;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderWriter
{
    public function save(?Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $order ??= new Order;
            $status = OrderStatus::query()->findOrFail($data['order_status_id']);
            $goods = $this->goodsFor($data['items']);
            $lines = collect($data['items'])
                ->map(fn (array $item) => $this->makeLine($goods[(int) $item['good_id']], $item, $data['currency_code']))
                ->values();

            $order->fill([
                'number' => $data['number'] ?? $order->number,
                'entity_id' => $data['entity_id'],
                'order_status_id' => $status->id,
                'created_by_user_id' => $data['created_by_user_id'] ?? $order->created_by_user_id,
                'contact_telephone_id' => array_key_exists('contact_telephone_id', $data)
                    ? $data['contact_telephone_id']
                    : $order->contact_telephone_id,
                'preferred_delivery_time' => $data['preferred_delivery_time'] ?? null,
                'internal_comment' => $data['internal_comment'] ?? null,
                'currency_code' => strtoupper($data['currency_code']),
                'total_amount' => $lines->sum(fn (array $line) => (float) ($line['line_total'] ?? 0)),
                'total_weight' => $lines->sum(fn (array $line) => (float) ($line['line_weight'] ?? 0)) ?: null,
                'submitted_at' => $data['submitted_at'] ?? $order->submitted_at ?? now(),
                'closed_at' => $status->is_closed
                    ? ($order->closed_at ?? now())
                    : null,
            ]);
            $order->save();
            $order->unsetRelation('entity');

            $order->items()->delete();
            $order->items()->createMany($lines->all());

            $buildingIds = collect($data['building_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $order->buildings()->sync(
                $buildingIds
                    ->mapWithKeys(fn (int $buildingId, int $position) => [
                        $buildingId => [
                            'role' => $position === 0 ? 'delivery' : 'logistics',
                            'position' => $position,
                        ],
                    ])
                    ->all()
            );

            if ($buildingIds->isNotEmpty()) {
                $order->entity?->buildings()->syncWithoutDetaching($buildingIds->all());
            }

            return $order->fresh($this->relations());
        });
    }

    public function relations(): array
    {
        return [
            'status',
            'entity.units:id,name',
            'createdBy:id,name,email',
            'contactTelephone:id,number',
            'buildings.city.region',
            'buildings.buildingType',
            'items.good:id,name,slug',
        ];
    }

    private function goodsFor(array $items): Collection
    {
        return Good::query()
            ->whereIn('id', collect($items)->pluck('good_id')->unique())
            ->with([
                'country:id,name',
                'publishedMedia' => fn ($query) => $query
                    ->where('type', 'image')
                    ->orderByDesc('is_ava')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->get()
            ->keyBy('id');
    }

    private function makeLine(Good $good, array $item, string $currencyCode): array
    {
        $quantity = round((float) $item['quantity'], 3);
        $unitPrice = filled($item['unit_price'] ?? null)
            ? round((float) $item['unit_price'], 4)
            : null;
        $denominator = is_numeric($good->denominator) && (float) $good->denominator > 0
            ? (float) $good->denominator
            : null;
        $media = $good->publishedMedia->first();

        return [
            'good_id' => $good->id,
            'good_name' => $good->name,
            'good_slug' => $good->slug,
            'image_url' => $media?->url
                ?: $media?->thumb_url
                ?: $good->ava_thumb
                ?: $good->ava_image,
            'quantity' => $quantity,
            'denominator' => $denominator,
            'line_weight' => $denominator !== null
                ? round($denominator * $quantity, 4)
                : null,
            'price_gross' => $unitPrice,
            'currency_code' => strtoupper($currencyCode),
            'line_total' => $unitPrice !== null
                ? round($unitPrice * $quantity, 4)
                : null,
            'country_name' => $good->country?->name,
            'snapshot' => [
                'good_id' => $good->id,
                'name' => $good->name,
                'slug' => $good->slug,
            ],
        ];
    }
}
