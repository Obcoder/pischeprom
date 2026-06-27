<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\EntityClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class EntityLookupController extends Controller
{
    public function lookup(Request $request)
    {
        $validated = $request->validate([
                                            'query' => ['required', 'string', 'min:2', 'max:255'],
                                        ]);

        $query = trim($validated['query']);
        $digits = preg_replace('/\D+/', '', $query);

        if (in_array(strlen($digits), [10, 12], true)) {
            return $this->lookupDaData(
                endpoint: 'findById/party',
                query: $digits,
                count: 8,
            );
        }

        return $this->lookupDaData(
            endpoint: 'suggest/party',
            query: $query,
            count: 10,
        );
    }

    public function lookupByInn(Request $request)
    {
        $validated = $request->validate([
                                            'inn' => ['required', 'string', 'regex:/^(?:\d{10}|\d{12})$/'],
                                        ]);

        return $this->lookupDaData(
            endpoint: 'findById/party',
            query: $validated['inn'],
            count: 5,
        );
    }

    public function buildingPostcode(Request $request)
    {
        $validated = $request->validate([
                                            'city_id' => ['required', 'integer', 'exists:cities,id'],
                                            'address' => ['required', 'string', 'min:2', 'max:255'],
                                        ]);

        $city = City::query()->findOrFail($validated['city_id']);
        $cityName = trim((string) $city->name);
        $address = trim($validated['address']);
        $query = trim(implode(', ', array_filter([$cityName, $address])));

        $cleanAddress = $this->lookupDaDataCleanAddress($query);

        if ($cleanAddress !== null) {
            return response()->json([
                                        'data' => $cleanAddress,
                                        'suggestions' => [$cleanAddress],
                                    ]);
        }

        $response = $this->requestDaData('suggest/address', [
            'query' => $query,
            'count' => 5,
            'locations_boost' => array_filter([
                $cityName !== '' ? ['city' => $cityName] : null,
            ]),
            'to_bound' => ['value' => 'house'],
        ]);

        $suggestions = collect($response['suggestions'] ?? [])
            ->map(fn (array $item) => $this->mapDaDataAddressToPostcodePayload($item))
            ->filter(fn (array $item) => filled($item['postcode']))
            ->values();

        return response()->json([
                                    'data' => $suggestions->first(),
                                    'suggestions' => $suggestions,
                                ]);
    }

    protected function lookupDaData(string $endpoint, string $query, int $count)
    {
        $response = $this->requestDaData($endpoint, [
            'query' => $query,
            'count' => $count,
        ]);

        $items = collect($response['suggestions'] ?? [])
            ->map(fn (array $item) => $this->mapDaDataPartyToEntityPayload($item))
            ->values();

        return response()->json([
                                    'data' => $items,
                                ]);
    }

    protected function requestDaData(string $endpoint, array $payload): array
    {
        $token = config('services.dadata.token');

        abort_unless($token, 503, 'DaData token is not configured.');

        return Http::timeout(8)
            ->withHeaders([
                              'Authorization' => 'Token ' . $token,
                              'Content-Type' => 'application/json',
                              'Accept' => 'application/json',
                          ])
            ->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/{$endpoint}", $payload)
            ->throw()
            ->json();
    }

    protected function lookupDaDataCleanAddress(string $query): ?array
    {
        if (! config('services.dadata.secret')) {
            return null;
        }

        try {
            $response = $this->requestDaDataClean('address', [$query]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $item = collect($response)->first();

        if (! is_array($item) || ! filled($item['postal_code'] ?? null)) {
            return null;
        }

        return $this->mapDaDataCleanAddressToPostcodePayload($item);
    }

    protected function requestDaDataClean(string $endpoint, array $payload): array
    {
        $token = config('services.dadata.token');
        $secret = config('services.dadata.secret');

        abort_unless($token && $secret, 503, 'DaData clean credentials are not configured.');

        return Http::timeout(8)
            ->withHeaders([
                              'Authorization' => 'Token ' . $token,
                              'X-Secret' => $secret,
                              'Content-Type' => 'application/json',
                              'Accept' => 'application/json',
                          ])
            ->post("https://cleaner.dadata.ru/api/v1/clean/{$endpoint}", $payload)
            ->throw()
            ->json();
    }

    protected function mapDaDataPartyToEntityPayload(array $item): array
    {
        $data = $item['data'] ?? [];
        $name = $data['name'] ?? [];
        $opf = $data['opf'] ?? [];
        $address = $data['address'] ?? [];
        $addressData = $address['data'] ?? [];
        $management = $data['management'] ?? [];
        $state = $data['state'] ?? [];

        $inn = $data['inn'] ?? null;
        $opfShort = $opf['short'] ?? null;

        return [
            'entity' => [
                'name' => $name['short_with_opf']
                    ?? $item['value']
                        ?? $name['full_with_opf']
                        ?? null,

                'full_name' => $name['full_with_opf'] ?? null,

                'entity_classification_id' => $this->resolveClassificationId(
                    inn: $inn,
                    opfShort: $opfShort,
                    dadataType: $data['type'] ?? null,
                ),

                'entity_classification_name' => $this->resolveClassificationName(
                    inn: $inn,
                    opfShort: $opfShort,
                    dadataType: $data['type'] ?? null,
                ),

                'INN' => $inn,
                'KPP' => $data['kpp'] ?? null,
                'OGRN' => $data['ogrn'] ?? null,

                'legal_address' => $address['unrestricted_value'] ?? null,
                'country_name' => $addressData['country'] ?? 'Россия',

                'opf' => $opfShort,
                'okved' => $data['okved'] ?? null,

                'director_name' => $management['name'] ?? null,
                'director_post' => $management['post'] ?? null,

                'status' => $state['status'] ?? null,
                'registration_date' => $state['registration_date'] ?? null,
                'liquidation_date' => $state['liquidation_date'] ?? null,
            ],

            'raw' => $item,
        ];
    }

    protected function mapDaDataAddressToPostcodePayload(array $item): array
    {
        $data = $item['data'] ?? [];

        return [
            'postcode' => $data['postal_code'] ?? null,
            'value' => $item['value'] ?? null,
            'unrestricted_value' => $item['unrestricted_value'] ?? null,
            'city' => $data['city'] ?? $data['settlement'] ?? null,
            'street' => $data['street_with_type'] ?? null,
            'house' => trim(implode(' ', array_filter([
                $data['house_type'] ?? null,
                $data['house'] ?? null,
                $data['block_type'] ?? null,
                $data['block'] ?? null,
            ]))),
            'source' => 'dadata_suggest',
            'raw' => $item,
        ];
    }

    protected function mapDaDataCleanAddressToPostcodePayload(array $item): array
    {
        return [
            'postcode' => $item['postal_code'] ?? null,
            'value' => $item['result'] ?? $item['source'] ?? null,
            'unrestricted_value' => $item['result'] ?? null,
            'city' => $item['city'] ?? $item['settlement'] ?? null,
            'street' => $item['street_with_type'] ?? null,
            'house' => trim(implode(' ', array_filter([
                $item['house_type'] ?? null,
                $item['house'] ?? null,
                $item['block_type'] ?? null,
                $item['block'] ?? null,
            ]))),
            'source' => 'dadata_clean',
            'raw' => $item,
        ];
    }

    protected function resolveClassificationName(?string $inn, ?string $opfShort, ?string $dadataType): string
    {
        $opfShort = trim((string) $opfShort);

        if ($opfShort !== '') {
            if (str_contains(mb_strtoupper($opfShort), 'ИП')) {
                return 'ИП';
            }

            if (str_contains(mb_strtoupper($opfShort), 'ООО')) {
                return 'ООО';
            }

            if (str_contains(mb_strtoupper($opfShort), 'АО')) {
                return 'АО';
            }
        }

        if ($dadataType === 'INDIVIDUAL' || mb_strlen((string) $inn) === 12) {
            return 'ИП';
        }

        return 'ООО';
    }

    protected function resolveClassificationId(?string $inn, ?string $opfShort, ?string $dadataType): ?int
    {
        $name = $this->resolveClassificationName($inn, $opfShort, $dadataType);

        return EntityClassification::query()
            ->where('name', $name)
            ->value('id');
    }
}
