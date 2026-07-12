<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CustomerOrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function store(Request $request, CustomerOrderNotificationService $notificationService): JsonResponse
    {
        $this->normalizeItems($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.good_id' => ['required', 'integer', 'exists:goods,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:1', 'max:999'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $rawItems = collect($validated['items']);
        $goods = $this->publishedGoods($rawItems->pluck('good_id')->unique()->values());

        if ($goods->count() !== $rawItems->pluck('good_id')->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Один из товаров уже недоступен для заказа.',
            ]);
        }

        $lines = $rawItems
            ->map(fn (array $item) => $this->makeOrderLine($goods[(int) $item['good_id']], (float) ($item['quantity'] ?? 1)))
            ->values();

        $order = DB::transaction(function () use ($request, $user, $lines): Order {
            $order = Order::query()->create([
                'number' => $this->makeOrderNumber(),
                'user_id' => $user->id,
                'status' => 'new',
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'customer_account_type' => $user->account_type,
                'customer_city_name' => $user->relationLoaded('city')
                    ? $user->city?->name
                    : $user->city()->value('name'),
                'customer_entity_name' => $this->primaryEntityName($user),
                'total_amount' => $lines->sum(fn (array $line) => (float) ($line['line_total'] ?? 0)),
                'total_weight' => $lines->sum(fn (array $line) => (float) ($line['line_weight'] ?? 0)) ?: null,
                'currency_code' => $lines->first()['currency_code'] ?? 'RUB',
                'submitted_at' => now(),
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            $order->items()->createMany($lines->all());

            return $order->load('items');
        });

        $notificationService->notify($order);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
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
            ])
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
            ->with([
                'country:id,name,flag',
                'priceTypeValues' => function ($query): void {
                    $query
                        ->where('is_published', true)
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
                'description',
            ])
            ->keyBy('id');
    }

    private function primaryEntityName(User $user): ?string
    {
        if (! method_exists($user, 'entities') || ! Schema::hasTable('entity_user')) {
            return null;
        }

        $query = $user->entities();

        if (Schema::hasColumn('entity_user', 'is_primary')) {
            $query->wherePivot('is_primary', true);
        }

        return $query->value('name');
    }

    private function makeOrderLine(Good $good, float $quantity): array
    {
        $quantity = max(1, $quantity);
        $denominator = is_numeric($good->denominator) && (float) $good->denominator > 0
            ? (float) $good->denominator
            : null;
        $price = $this->selectedPrice($good);
        $priceGross = $this->priceValue($price);
        $currencyCode = $this->currencyCode($price);
        $lineTotal = $priceGross !== null
            ? round($priceGross * $quantity, 4)
            : null;
        $lineWeight = $denominator !== null
            ? round($denominator * $quantity, 4)
            : null;

        return [
            'good_id' => $good->id,
            'good_name' => $good->name,
            'good_slug' => $good->slug,
            'image_url' => $this->primaryImage($good),
            'quantity' => $quantity,
            'denominator' => $denominator,
            'line_weight' => $lineWeight,
            'price_gross' => $priceGross,
            'currency_code' => $currencyCode,
            'line_total' => $lineTotal,
            'country_name' => $good->country?->name,
            'snapshot' => [
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
            ->sortBy(fn (GoodPriceTypeValue $price) => $price->priceType?->sort_order ?? 100)
            ->values();

        return $prices->first(fn (GoodPriceTypeValue $price) => $this->isPartnerPrice($price))
            ?: $prices->first(fn (GoodPriceTypeValue $price) => $this->isRetailPrice($price))
            ?: $prices->first(fn (GoodPriceTypeValue $price) => (bool) $price->priceType?->is_public)
            ?: $prices->first();
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
        return Str::lower(trim(($price->priceType?->code ?? '') . ' ' . ($price->priceType?->name ?? '')));
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

    private function makeOrderNumber(): string
    {
        do {
            $number = 'PP-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
