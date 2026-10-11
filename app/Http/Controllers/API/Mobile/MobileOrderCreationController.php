<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Mobile\MobileAccess;
use App\Services\Orders\MobileOrderPresenter;
use App\Services\Orders\OrderDataValidator;
use App\Services\Orders\OrderWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileOrderCreationController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $this->authorizeCreation($request);
        $statuses = OrderStatus::query()->where('is_closed', false)->ordered()
            ->get(['id', 'code', 'name', 'color', 'is_closed']);

        return response()->json([
            'statuses' => $statuses,
            'default_status_id' => $statuses->firstWhere('code', OrderStatus::OPEN)?->id ?? $statuses->first()?->id,
            'currency_codes' => Currency::query()->whereNotNull('code')->orderBy('code')->pluck('code')
                ->push('RUB')->filter()->map(fn ($code) => strtoupper((string) $code))->unique()->values(),
        ]);
    }

    public function entities(Request $request): JsonResponse
    {
        $this->authorizeCreation($request);
        $data = $this->pickerFilters($request);
        $entities = Entity::query()->withoutEagerLoads()->with([
            'telephones:id,number',
            'buildings' => fn ($query) => $query->withoutEagerLoads()
                ->with(['city:id,name', 'apartments'])->orderBy('address'),
        ])->search($data['search'] ?? null)->orderBy('name')->orderBy('id')
            ->paginate($data['per_page'] ?? 20, ['id', 'name', 'full_name', 'INN']);

        return response()->json([
            'data' => $entities->getCollection()->map(fn (Entity $entity) => [
                ...$entity->only(['id', 'name', 'full_name', 'INN']),
                'telephones' => $entity->telephones->map->only(['id', 'number'])->values(),
                'buildings' => $entity->buildings->map(fn (Building $building) => [
                    'id' => $building->id,
                    'address' => $building->address,
                    'city' => $building->city?->name,
                    'full_address' => collect([$building->city?->name, $building->address])->filter()->unique()->implode(', '),
                    'apartment_id' => $building->pivot->apartment_id,
                    'apartments' => $building->apartments->map->only(['id', 'number', 'type', 'label'])->values(),
                ])->values(),
            ]),
            'meta' => $this->pagination($entities),
        ]);
    }

    public function goods(Request $request): JsonResponse
    {
        $this->authorizeCreation($request);
        $data = $this->pickerFilters($request);
        $goods = Good::query()->with(['priceTypeValues' => fn ($query) => $query
            ->with(['currency:id,code', 'priceType.currency:id,code'])
            ->whereHas('priceType', fn (Builder $type) => $type->where('is_active', true))
            ->whereNotNull('price_gross')->where('price_gross', '>=', 0)
            ->where(fn (Builder $date) => $date->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn (Builder $date) => $date->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))])
            ->search($data['search'] ?? null)->orderBy('name')->orderBy('id')
            ->paginate($data['per_page'] ?? 20, ['id', 'name', 'measure_id', 'unit_weight_kg']);

        return response()->json([
            'data' => $goods->getCollection()->map(fn (Good $good) => [
                ...$good->only(['id', 'name', 'measure_id']),
                'measurement' => $good->measurement(),
                'prices' => $good->priceTypeValues->sortBy(fn (GoodPriceTypeValue $price) => $price->priceType->sort_order)
                    ->map(fn (GoodPriceTypeValue $price) => [
                        'id' => $price->id,
                        'unit_price' => $price->price_gross,
                        'price_gross' => $price->price_gross,
                        'currency_code' => strtoupper($price->currency?->code ?: $price->priceType?->currency?->code ?: 'RUB'),
                        'price_type' => $price->priceType->only(['id', 'name', 'code']),
                    ])->values(),
            ]),
            'meta' => $this->pagination($goods),
        ]);
    }

    public function store(Request $request, OrderDataValidator $validator, OrderWriter $writer, MobileOrderPresenter $presenter): JsonResponse
    {
        $this->authorizeCreation($request);
        $identity = $request->validate(['request_id' => ['required', 'uuid']]);
        $payloadHash = hash('sha256', json_encode($this->sortKeys($request->except('request_id')), JSON_THROW_ON_ERROR));

        [$order, $created] = DB::transaction(function () use ($request, $identity, $payloadHash, $validator, $writer): array {
            // Serialize creation for this user, including the first request for a UUID.
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('mobile_order_creation_requests')->where('user_id', $request->user()->id)
                ->where('request_id', $identity['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $payloadHash), 409,
                    'Этот запрос уже использован для другого заказа.');
                abort_unless($existing->order_id, 410, 'Созданный по этому запросу заказ удалён.');

                return [Order::query()->findOrFail($existing->order_id), false];
            }

            // Replays precede mutable catalog and uniqueness validation: the original
            // submission already succeeded even if its references have since changed.
            $data = $validator->validated($request);
            $this->validateSelections($data);
            $order = $writer->save(null, [...$data, 'created_by_user_id' => $request->user()->id]);
            DB::table('mobile_order_creation_requests')->insert([
                'user_id' => $request->user()->id,
                'request_id' => $identity['request_id'],
                'payload_hash' => $payloadHash,
                'order_id' => $order->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [$order, true];
        }, 3);

        return response()->json(['data' => $presenter->one($order)], $created ? 201 : 200);
    }

    private function authorizeCreation(Request $request): void
    {
        abort_unless(MobileAccess::canCreateOrders($request->user()), 403, 'Недостаточно прав для создания заказов.');
    }

    private function sortKeys(array $payload): array
    {
        if (! array_is_list($payload)) {
            ksort($payload);
        }

        return array_map(fn ($value) => is_array($value) ? $this->sortKeys($value) : $value, $payload);
    }

    private function validateSelections(array $data): void
    {
        $entity = Entity::query()->withoutEagerLoads()->findOrFail($data['entity_id']);
        if (! empty($data['contact_telephone_id']) && ! $entity->telephones()->whereKey($data['contact_telephone_id'])->exists()) {
            throw ValidationException::withMessages(['contact_telephone_id' => 'Выберите телефон покупателя.']);
        }
        $buildingIds = $data['building_ids'] ?? [];
        if ($entity->buildings()->whereKey($buildingIds)->count() !== count($buildingIds)) {
            throw ValidationException::withMessages(['building_ids' => 'Выберите адрес покупателя.']);
        }
        if (OrderStatus::query()->whereKey($data['order_status_id'])->where('is_closed', true)->exists()) {
            throw ValidationException::withMessages(['order_status_id' => 'Выберите открытый статус нового заказа.']);
        }
    }

    private function pickerFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
    }

    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
