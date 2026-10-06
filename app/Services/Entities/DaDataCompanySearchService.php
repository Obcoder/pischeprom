<?php

namespace App\Services\Entities;

use App\Models\Entity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use TypeError;

class DaDataCompanySearchService
{
    public function __construct(private readonly DaDataPartyMapper $mapper) {}

    public function search(array $filters): array
    {
        $token = config('services.dadata.token');
        abort_unless($token, 503, 'Поиск компаний через DaData не настроен. Обратитесь к администратору.');

        $payload = [
            'query' => trim($filters['query']),
            'okved' => array_values($filters['okved']),
            'count' => (int) ($filters['count'] ?? 20),
        ];

        $statuses = $filters['status'] ?? ['ACTIVE'];
        if ($statuses !== []) {
            $payload['status'] = array_values($statuses);
        }
        if (! empty($filters['type'])) {
            $payload['type'] = $filters['type'];
        }
        if (! empty($filters['region_code'])) {
            $payload['locations'] = [['kladr_id' => $filters['region_code']]];
        }

        try {
            // Always use suggest: findById ignores the OKVED filter.
            $response = Http::connectTimeout(3)->timeout(8)
                ->withToken($token, 'Token')->acceptJson()
                ->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', $payload);
        } catch (ConnectionException) {
            abort(503, 'Не удалось связаться с DaData. Повторите поиск позже.');
        }

        // Never expose upstream bodies (or credential-bearing request exceptions).
        if (in_array($response->status(), [401, 403], true)) {
            abort(503, 'DaData отклонила запрос: проверьте API-ключ, подтверждение почты и дневной лимит.');
        }
        if ($response->status() === 429) {
            abort(429, 'Превышена частота запросов к DaData. Повторите поиск через минуту.');
        }
        abort_unless($response->successful(), 503, 'Поиск DaData временно недоступен. Повторите позже.');

        $suggestions = $response->json('suggestions');
        abort_unless(is_array($suggestions) && array_is_list($suggestions), 502, 'DaData вернула некорректный ответ.');
        foreach ($suggestions as $suggestion) {
            abort_unless(
                is_array($suggestion) && is_array($suggestion['data'] ?? null),
                502,
                'DaData вернула некорректный ответ.',
            );
        }

        try {
            $items = collect($suggestions)->take($payload['count'])
                ->map(fn (array $item) => $this->mapper->map($item));
        } catch (TypeError) {
            abort(502, 'DaData вернула некорректные реквизиты компании.');
        }
        $inns = $items->pluck('entity.INN')->filter()->unique()->values();
        $existing = $inns->isEmpty() ? collect() : Entity::query()->withoutEagerLoads()
            ->select(['id', 'name', 'INN'])
            ->whereIn('INN', $inns)
            ->with(['units' => fn ($query) => $query->withoutEagerLoads()->select(['units.id', 'units.name'])])
            ->get()->groupBy('INN');

        return [
            'data' => $items->map(function (array $item) use ($existing): array {
                $item['existing_entities'] = $existing->get($item['entity']['INN'], collect())
                    ->map(fn (Entity $entity) => [
                        'id' => $entity->id,
                        'name' => $entity->name,
                        'units' => $entity->units->map(fn ($unit) => [
                            'id' => $unit->id,
                            'name' => $unit->name,
                        ])->values()->all(),
                    ])->values()->all();

                return $item;
            })->values()->all(),
            'meta' => [
                'source' => 'DaData',
                'limit' => $payload['count'],
                'returned' => $items->count(),
                'exhaustive' => false,
                'okved_match' => 'main_exact',
            ],
        ];
    }
}
