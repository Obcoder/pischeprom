<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Entities\UserEntityResolver;
use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\PublicGoodOffer;
use App\Services\Orders\CustomerOrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function store(
        Request $request,
        CustomerOrderNotificationService $notificationService,
        UserEntityResolver $entityResolver
    ): JsonResponse {
        $this->normalizeItems($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.good_id' => ['required', 'integer', 'exists:goods,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001', 'max:9999', 'decimal:0,3'],
            'items.*.pricing_context' => ['nullable', Rule::in(['public', 'catalog'])],
            'items.*.measure_id' => ['nullable', 'integer', 'exists:measures,id'],
            'items.*.measurement' => ['nullable', 'array:measure_id,unit_label,kilograms_per_unit'],
            'items.*.measurement.measure_id' => ['nullable', 'integer'],
            'items.*.measurement.unit_label' => ['nullable', 'string', 'max:255'],
            'items.*.measurement.kilograms_per_unit' => ['nullable', 'numeric', 'gt:0'],
            'delivery_address' => ['required', 'string', 'max:3000'],
            'delivery_apartment_number' => ['nullable', 'string', 'max:50'],
            'delivery_apartment_type' => ['nullable', Rule::in(['apartment', 'office', 'premise'])],
            'preferred_delivery_time' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:64'],
            'customer_phone_source' => ['nullable', 'string', 'in:profile,manual'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $rawItems = collect($validated['items']);
        $entity = $user->entities()
            ->wherePivot('is_primary', true)
            ->first()
            ?: $user->entities()->first()
            ?: $entityResolver->resolve($user);
        $canManageEntity = $entityResolver->canManageEntity($entity, $user);
        $contactTelephone = $canManageEntity
            ? $entityResolver->attachPhone($entity, $validated['customer_phone'])
            : $entityResolver->resolvePhone($validated['customer_phone']);
        $statusId = OrderStatus::query()
            ->where('code', OrderStatus::OPEN)
            ->value('id');

        $order = DB::transaction(function () use (
            $user,
            $entity,
            $contactTelephone,
            $rawItems,
            $validated,
            $statusId,
            $canManageEntity
        ): Order {
            $goods = $this->publishedGoods($rawItems->pluck('good_id')->unique()->values());

            if ($goods->count() !== $rawItems->pluck('good_id')->unique()->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Один из товаров уже недоступен для заказа.',
                ]);
            }

            $lines = $rawItems
                ->map(fn (array $item, int $index) => $this->makeOrderLine(
                    $goods[(int) $item['good_id']],
                    (float) ($item['quantity'] ?? 1),
                    $item['pricing_context'] ?? 'catalog',
                    isset($item['measure_id']) ? (int) $item['measure_id'] : null,
                    $item['measurement'] ?? null,
                    $index,
                ))
                ->values();

            $address = trim($validated['delivery_address']);
            $building = Building::query()->firstOrCreate([
                'city_id' => $user->city_id,
                'address' => $address,
            ]);
            $apartmentNumber = trim((string) ($validated['delivery_apartment_number'] ?? ''));
            $apartment = $apartmentNumber !== '' ? $building->apartments()->firstOrCreate([
                'number' => $apartmentNumber,
                'type' => $validated['delivery_apartment_type'] ?? 'apartment',
            ]) : null;

            if ($canManageEntity && ! $entity->buildings()->whereKey($building->id)->exists()) {
                $entity->buildings()->attach($building->id, ['apartment_id' => $apartment?->id]);
            }

            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'entity_id' => $entity->id,
                'order_status_id' => $statusId,
                'created_by_user_id' => $user->id,
                'contact_telephone_id' => $contactTelephone?->id,
                'preferred_delivery_time' => $validated['preferred_delivery_time'],
                'total_amount' => $lines->sum(fn (array $line) => (float) ($line['line_total'] ?? 0)),
                'total_weight' => $lines->contains(fn (array $line) => $line['line_weight'] === null)
                    ? null : $lines->sum('line_weight'),
                'currency_code' => $lines->first()['currency_code'] ?? 'RUB',
                'submitted_at' => now(),
            ]);

            $order->items()->createMany($lines->all());
            $order->buildings()->attach($building->id, [
                'role' => 'delivery',
                'position' => 0,
                'apartment_id' => $apartment?->id,
            ]);

            return $order->load([
                'status',
                'entity.emails',
                'entity.telephones',
                'createdBy',
                'contactTelephone',
                'buildings',
                'items',
            ]);
        });

        $notificationService->notify($order);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status?->code,
                'total_amount' => $order->total_amount,
                'total_weight' => $order->total_weight,
                'currency_code' => $order->currency_code,
            ],
            'redirect' => route('dashboard'),
        ], 201);
    }

    private function normalizeItems(Request $request): void
    {
        $items = collect($request->input('items', []))
            ->map(fn ($item) => [
                'good_id' => data_get($item, 'good_id', data_get($item, 'id')),
                'quantity' => data_get($item, 'quantity', 1),
                'measure_id' => data_get($item, 'measure_id'),
                'measurement' => data_get($item, 'measurement'),
                'pricing_context' => data_get($item, 'pricing_context'),
            ])
            ->values()
            ->all();

        $request->merge([
            'items' => $items,
        ]);
    }

    private function publishedGoods(Collection $goodIds): Collection
    {
        return Good::query()
            ->whereIn('id', $goodIds)
            ->where('is_published', true)
            ->sharedLock()
            ->with([
                'country:id,name,flag',
                'priceTypeValues' => function ($query): void {
                    $query
                        ->where('is_published', true)
                        ->where(fn ($dates) => $dates->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
                        ->where(fn ($dates) => $dates->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
                        ->whereHas('priceType', fn ($priceTypeQuery) => $priceTypeQuery->where('is_active', true))
                        ->with(['priceType.currency', 'currency'])
                        ->orderByDesc('updated_at');
                },
                'publishedMedia' => function ($query): void {
                    $query
                        ->where('type', 'image')
                        ->where('is_published', true)
                        ->orderByDesc('is_ava')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->get([
                'id',
                'country_id',
                'name',
                'slug',
                'ava_image',
                'ava_thumb',
                'denominator',
                'measure_id',
                'unit_weight_kg',
                'description',
            ])
            ->keyBy('id');
    }

    private function makeOrderLine(Good $good, float $quantity, string $pricingContext, ?int $measureId = null, ?array $snapshot = null, int $index = 0): array
    {
        $measurement = app(GoodMeasurement::class)->assertConfigured($good, "items.{$index}.measurement");
        app(GoodMeasurement::class)->assertMeasure($good, $measureId, "items.{$index}.measure_id");
        app(GoodMeasurement::class)->assertSnapshot($good, $snapshot, "items.{$index}.measurement");
        $quantity = round($quantity, 3);
        $denominator = $measurement['kilograms_per_unit'];
        $price = $pricingContext === 'public'
            ? app(PublicGoodOffer::class)->pricesFor($good)->first()
            : $this->selectedPrice($good);
        $priceGross = $this->priceValue($price);
        $currencyCode = $this->currencyCode($price);
        $lineTotal = $priceGross !== null
            ? round($priceGross * $quantity, 4)
            : null;
        $lineWeight = $denominator !== null
            ? round($denominator * $quantity, 6)
            : null;

        return [
            'good_id' => $good->id,
            'good_name' => $good->name,
            'good_slug' => $good->slug,
            'image_url' => $this->primaryImage($good),
            'quantity' => $quantity,
            'measure_id' => $measurement['measure_id'],
            'denominator' => $denominator,
            'line_weight' => $lineWeight,
            'price_gross' => $priceGross,
            'currency_code' => $currencyCode,
            'line_total' => $lineTotal,
            'country_name' => $good->country?->name,
            'snapshot' => [
                'measurement' => $measurement,
                'description' => $good->description,
                'price_type' => $price?->priceType
                    ? [
                        'id' => $price->priceType->id,
                        'code' => $price->priceType->code,
                        'name' => $price->priceType->name,
                    ]
                    : null,
            ],
        ];
    }

    private function selectedPrice(Good $good): ?GoodPriceTypeValue
    {
        $prices = $good->priceTypeValues
            ->filter(fn (GoodPriceTypeValue $price) => $price->is_published !== false)
            ->filter(fn (GoodPriceTypeValue $price) => $this->priceValue($price) !== null)
            ->sortBy(fn (GoodPriceTypeValue $price) => $price->priceType?->sort_order ?? 100)
            ->values();

        return $prices->first(fn (GoodPriceTypeValue $price) => $this->isPartnerPrice($price))
            ?: $prices->first(fn (GoodPriceTypeValue $price) => $this->isRetailPrice($price))
            ?: $prices->first(fn (GoodPriceTypeValue $price) => (bool) $price->priceType?->is_public);
    }

    private function isPartnerPrice(GoodPriceTypeValue $price): bool
    {
        $text = $this->priceTypeText($price);

        return str_contains($text, 'partner')
            || str_contains($text, 'партн')
            || str_contains($text, 'дилер')
            || str_contains($text, 'dealer')
            || str_contains($text, 'diler');
    }

    private function isRetailPrice(GoodPriceTypeValue $price): bool
    {
        $text = $this->priceTypeText($price);

        return str_contains($text, 'retail')
            || str_contains($text, 'rozn')
            || str_contains($text, 'рознич')
            || str_contains($text, 'розница');
    }

    private function priceTypeText(GoodPriceTypeValue $price): string
    {
        return Str::lower(trim(($price->priceType?->code ?? '').' '.($price->priceType?->name ?? '')));
    }

    private function priceValue(?GoodPriceTypeValue $price): ?float
    {
        $value = $price?->price_gross ?? $price?->price_net;

        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }

    private function currencyCode(?GoodPriceTypeValue $price): string
    {
        return $price?->currency?->code
            ?: $price?->priceType?->currency?->code
            ?: 'RUB';
    }

    private function primaryImage(Good $good): ?string
    {
        $media = $good->publishedMedia->first();

        return $media?->url
            ?: $media?->thumb_url
            ?: $good->ava_thumb
            ?: $good->ava_image;
    }
}
