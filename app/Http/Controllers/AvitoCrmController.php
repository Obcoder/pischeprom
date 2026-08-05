<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\AvitoChat;
use App\Models\AvitoContactCandidate;
use App\Models\BuildingType;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\Good;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Telephone;
use App\Services\Avito\AvitoContactDetector;
use App\Services\Avito\AvitoCrmOutboundService;
use App\Services\Avito\AvitoCrmService;
use App\Services\Orders\OrderWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvitoCrmController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'entity_classifications' => EntityClassification::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'countries' => Country::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'building_types' => BuildingType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'order_statuses' => OrderStatus::query()
                ->ordered()
                ->get(['id', 'code', 'name', 'color', 'is_closed']),
            'currency_codes' => Currency::query()
                ->whereNotNull('code')
                ->orderBy('code')
                ->pluck('code')
                ->push('RUB')
                ->filter()
                ->map(fn ($code) => strtoupper((string) $code))
                ->unique()
                ->values(),
        ]);
    }

    public function entities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $items = Entity::query()
            ->withoutEagerLoads()
            ->with(['classification:id,name', 'telephones:id,number'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $digits = preg_replace('/\D+/', '', $search) ?: '';
                $query->where(function (Builder $nested) use ($needle, $digits): void {
                    $nested->where('name', 'like', $needle)
                        ->orWhere('full_name', 'like', $needle)
                        ->orWhere('INN', 'like', $needle)
                        ->when($digits !== '', fn (Builder $phone) => $phone->orWhereHas(
                            'telephones',
                            fn (Builder $telephone) => $telephone->where('number', 'like', '%'.$digits.'%')
                        ));
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['items' => $items->map(fn (Entity $entity) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'full_name' => $entity->full_name,
            'INN' => $entity->INN,
            'classification' => $entity->classification?->name,
            'telephones' => $entity->telephones->pluck('number')->values(),
        ])->values()]);
    }

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $cities = City::query()
            ->withoutEagerLoads()
            ->with('region.country:id,name')
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'region_id']);

        return response()->json(['items' => $cities->map(fn (City $city) => [
            'id' => $city->id,
            'name' => $city->name,
            'region' => $city->region?->name,
            'country' => $city->region?->country?->name,
            'label' => collect([$city->name, $city->region?->name, $city->region?->country?->name])
                ->filter()
                ->unique()
                ->implode(' · '),
        ])->values()]);
    }

    public function goods(Request $request, AvitoCrmOutboundService $outbound): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $goods = Good::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with($outbound->goodRelations())
            ->withExists('stockMovements')
            ->orderByDesc('is_published')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'items' => $goods->map(fn (Good $good) => $outbound->goodPayload($good))->values(),
        ]);
    }

    public function show(
        Request $request,
        AvitoChat $chat,
        AvitoContactDetector $detector,
        OrderWriter $orderWriter,
    ): JsonResponse {
        $detector->detectChat($chat);
        $chat->load([
            'entity.classification:id,name',
            'entity.country:id,name',
            'entity.telephones:id,number',
            'entity.buildings.city.region.country',
            'entity.buildings.buildingType',
        ]);
        $candidates = AvitoContactCandidate::query()
            ->with([
                'message:id,avito_chat_id,text,remote_created_at',
                'telephone:id,number',
                'building.city.region',
            ])
            ->whereHas('message', fn (Builder $message) => $message->where('avito_chat_id', $chat->id))
            ->latest('id')
            ->limit(100)
            ->get();
        $phoneMatches = Telephone::query()
            ->with('entities:id,name')
            ->whereIn('number', $candidates
                ->where('type', AvitoContactCandidate::TYPE_PHONE)
                ->pluck('normalized_value')
                ->filter()
                ->unique())
            ->get()
            ->keyBy('number');
        $orders = Order::query()
            ->with($orderWriter->relations())
            ->withCount('items')
            ->whereHas('avitoChats', fn (Builder $query) => $query->whereKey($chat->id))
            ->latest('submitted_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json([
            'chat_id' => $chat->id,
            'entity' => $chat->entity ? $this->entityPayload($chat->entity) : null,
            'candidates' => $candidates->map(fn (AvitoContactCandidate $candidate) => [
                'id' => $candidate->id,
                'message_id' => $candidate->avito_message_id,
                'type' => $candidate->type,
                'raw_value' => $candidate->raw_value,
                'normalized_value' => $candidate->normalized_value,
                'confidence' => $candidate->confidence,
                'status' => $candidate->status,
                'telephone_id' => $candidate->telephone_id,
                'building_id' => $candidate->building_id,
                'resolved_at' => $candidate->resolved_at,
                'message_at' => $candidate->message?->remote_created_at,
                'message_excerpt' => mb_substr((string) $candidate->message?->text, 0, 180),
                'matched_entities' => $candidate->type === AvitoContactCandidate::TYPE_PHONE
                    ? ($phoneMatches->get($candidate->normalized_value)?->entities?->map(fn (Entity $entity) => [
                        'id' => $entity->id,
                        'name' => $entity->name,
                    ])->values() ?? collect())
                    : collect(),
            ])->values(),
            'orders' => OrderResource::collection($orders)->resolve($request),
        ]);
    }

    public function linkEntity(Request $request, AvitoChat $chat, AvitoCrmService $crm): JsonResponse
    {
        $validated = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
        ]);
        $entity = Entity::query()->findOrFail($validated['entity_id']);
        $crm->linkEntity($chat, $entity);

        return response()->json(['message' => 'Клиент привязан ко всем чатам этого Avito-пользователя.']);
    }

    public function unlinkEntity(AvitoChat $chat, AvitoCrmService $crm): JsonResponse
    {
        $crm->unlinkEntity($chat);

        return response()->json(['message' => 'Связь чата с клиентом удалена.']);
    }

    public function createEntity(Request $request, AvitoChat $chat, AvitoCrmService $crm): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:1024'],
            'entity_classification_id' => ['nullable', 'integer', 'exists:entity_classifications,id'],
            'INN' => ['nullable', 'string', 'max:32'],
            'KPP' => ['nullable', 'string', 'max:32'],
            'OGRN' => ['nullable', 'string', 'max:32'],
            'legal_address' => ['nullable', 'string', 'max:1024'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'bank_account_number' => ['nullable', 'string', 'max:34'],
            'bank_name' => ['nullable', 'string', 'max:1024'],
            'bank_bic' => ['nullable', 'string', 'max:16'],
            'bank_corr_account' => ['nullable', 'string', 'max:34'],
        ]);
        $entity = $crm->createAndLinkEntity($chat, $validated);

        return response()->json([
            'message' => 'Клиент создан и привязан к переписке Avito.',
            'entity' => $this->entityPayload($entity),
        ], 201);
    }

    public function storeTelephone(Request $request, AvitoChat $chat, AvitoCrmService $crm): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['nullable', 'integer', 'exists:avito_contact_candidates,id', 'required_without:number'],
            'number' => ['nullable', 'string', 'max:40', 'required_without:candidate_id'],
        ]);
        $candidate = filled($validated['candidate_id'] ?? null)
            ? AvitoContactCandidate::query()->findOrFail($validated['candidate_id'])
            : null;
        $telephone = $crm->saveTelephone($chat, $candidate, $validated['number'] ?? null, $request->user());

        return response()->json([
            'message' => 'Телефон сохранён у клиента.',
            'telephone' => ['id' => $telephone->id, 'number' => $telephone->number],
        ], 201);
    }

    public function storeBuilding(Request $request, AvitoChat $chat, AvitoCrmService $crm): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['nullable', 'integer', 'exists:avito_contact_candidates,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'building_type_id' => ['nullable', 'integer', 'exists:building_types,id'],
            'address' => ['required', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
        ]);
        $candidate = filled($validated['candidate_id'] ?? null)
            ? AvitoContactCandidate::query()->findOrFail($validated['candidate_id'])
            : null;
        $building = $crm->saveBuilding($chat, $validated, $candidate, $request->user());

        return response()->json([
            'message' => 'Адрес создан и привязан к клиенту.',
            'building' => [
                'id' => $building->id,
                'address' => $building->address,
                'postcode' => $building->postcode,
                'city' => $building->city?->name,
            ],
        ], 201);
    }

    public function updateCandidate(
        Request $request,
        AvitoContactCandidate $candidate,
        AvitoCrmService $crm,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AvitoContactCandidate::STATUS_PENDING,
                AvitoContactCandidate::STATUS_REJECTED,
            ])],
        ]);
        $candidate = $validated['status'] === AvitoContactCandidate::STATUS_REJECTED
            ? $crm->rejectCandidate($candidate, $request->user())
            : $crm->reopenCandidate($candidate);

        return response()->json([
            'message' => $candidate->status === AvitoContactCandidate::STATUS_REJECTED
                ? 'Подсказка скрыта.'
                : 'Подсказка возвращена на проверку.',
            'candidate' => $candidate,
        ]);
    }

    public function storeOrder(
        Request $request,
        AvitoChat $chat,
        AvitoCrmService $crm,
        AvitoCrmOutboundService $outbound,
    ): JsonResponse {
        $validated = $request->validate([
            'order_status_id' => ['nullable', 'integer', 'exists:order_statuses,id'],
            'contact_telephone_id' => ['nullable', 'integer', 'exists:telephones,id'],
            'preferred_delivery_time' => ['nullable', 'string', 'max:255'],
            'internal_comment' => ['nullable', 'string', 'max:10000'],
            'currency_code' => ['required', 'string', 'min:3', 'max:8'],
            'building_ids' => ['nullable', 'array'],
            'building_ids.*' => ['integer', 'distinct', 'exists:buildings,id'],
            'source_message_id' => ['nullable', 'integer', 'exists:avito_messages,id'],
            'send_confirmation' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.good_id' => ['required', 'integer', 'distinct', 'exists:goods,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        $order = $crm->createOrder($chat, $validated, $request->user());
        $outboundResult = ! empty($validated['send_confirmation'])
            ? $outbound->sendOrderConfirmation($chat, $order)
            : ['sent' => 0, 'warnings' => []];

        return response()->json([
            'message' => 'Заказ создан из переписки Avito.',
            'order' => (new OrderResource($order))->resolve($request),
            'outbound' => $outboundResult,
        ], 201);
    }

    public function sendGood(
        Request $request,
        AvitoChat $chat,
        Good $good,
        AvitoCrmOutboundService $outbound,
    ): JsonResponse {
        $validated = $request->validate([
            'intro' => ['nullable', 'string', 'max:500'],
            'price_value_id' => ['nullable', 'integer', 'exists:good_price_type_values,id'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'include_description' => ['nullable', 'boolean'],
            'include_price' => ['nullable', 'boolean'],
            'include_stock' => ['nullable', 'boolean'],
            'include_link' => ['nullable', 'boolean'],
            'media_ids' => ['nullable', 'array', 'max:5'],
            'media_ids.*' => ['integer', 'distinct', 'exists:good_media,id'],
        ]);
        $result = $outbound->sendGood($chat, $good, $validated);

        return response()->json([
            'message' => $result['warnings'] === []
                ? 'Карточка товара отправлена в чат Avito.'
                : 'Текст отправлен, но часть фотографий пропущена.',
            ...$result,
        ], 201);
    }

    private function entityPayload(Entity $entity): array
    {
        $entity->loadMissing([
            'classification:id,name',
            'country:id,name',
            'telephones:id,number',
            'buildings.city.region.country',
            'buildings.buildingType',
        ]);

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'full_name' => $entity->full_name,
            'INN' => $entity->INN,
            'KPP' => $entity->KPP,
            'OGRN' => $entity->OGRN,
            'legal_address' => $entity->legal_address,
            'classification' => $entity->classification?->name,
            'country' => $entity->country?->name,
            'telephones' => $entity->telephones->map(fn (Telephone $telephone) => [
                'id' => $telephone->id,
                'number' => $telephone->number,
            ])->values(),
            'buildings' => $entity->buildings->map(fn ($building) => [
                'id' => $building->id,
                'address' => $building->address,
                'postcode' => $building->postcode,
                'city' => $building->city?->name,
                'region' => $building->city?->region?->name,
                'building_type' => $building->buildingType?->name,
                'label' => collect([$building->city?->name, $building->address])->filter()->implode(', '),
            ])->values(),
        ];
    }
}
