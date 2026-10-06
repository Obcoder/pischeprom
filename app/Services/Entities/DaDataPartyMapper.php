<?php

namespace App\Services\Entities;

use App\Models\EntityClassification;

class DaDataPartyMapper
{
    public function map(array $item): array
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
