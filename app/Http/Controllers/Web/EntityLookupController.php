<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EntityClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EntityLookupController extends Controller
{
    public function lookupByInn(Request $request)
    {
        $validated = $request->validate([
                                            'inn' => ['required', 'string', 'regex:/^(?:\d{10}|\d{12})$/'],
                                        ]);

        $token = config('services.dadata.token');

        abort_unless($token, 503, 'DaData token is not configured.');

        $response = Http::timeout(8)
            ->withHeaders([
                              'Authorization' => 'Token ' . $token,
                              'Content-Type' => 'application/json',
                              'Accept' => 'application/json',
                          ])
            ->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party', [
                'query' => $validated['inn'],
                'count' => 5,
            ])
            ->throw()
            ->json();

        $items = collect($response['suggestions'] ?? [])
            ->map(fn (array $item) => $this->mapDaDataPartyToEntityPayload($item))
            ->values();

        return response()->json([
                                    'data' => $items,
                                ]);
    }

    protected function mapDaDataPartyToEntityPayload(array $item): array
    {
        $data = $item['data'] ?? [];
        $name = $data['name'] ?? [];
        $opf = $data['opf'] ?? [];
        $address = $data['address'] ?? [];
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
