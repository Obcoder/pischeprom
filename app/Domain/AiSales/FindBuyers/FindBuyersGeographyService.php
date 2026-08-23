<?php

namespace App\Domain\AiSales\FindBuyers;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FindBuyersGeographyService
{
    /** @return array{country_id: ?int, region_id: ?int, city_id: ?int, label: ?string} */
    public function validate(?int $countryId, ?int $regionId, ?int $cityId): array
    {
        $country = $countryId ? DB::table('countries')->select(['id', 'name'])->where('id', $countryId)->first() : null;
        if ($countryId && ! $country) {
            throw ValidationException::withMessages(['country_id' => 'Selected country is unavailable.']);
        }

        $region = $regionId ? DB::table('regions')->select(['id', 'country_id', 'name'])->where('id', $regionId)->first() : null;
        if ($regionId && ! $region) {
            throw ValidationException::withMessages(['region_id' => 'Selected region is unavailable.']);
        }

        $city = $cityId ? DB::table('cities')->select(['id', 'region_id', 'name'])->where('id', $cityId)->first() : null;
        if ($cityId && ! $city) {
            throw ValidationException::withMessages(['city_id' => 'Selected city is unavailable.']);
        }

        if ($city) {
            $cityRegion = DB::table('regions')->select(['id', 'country_id', 'name'])->where('id', $city->region_id)->first();
            if (! $cityRegion || ($region && (int) $region->id !== (int) $cityRegion->id)) {
                throw ValidationException::withMessages(['city_id' => 'Selected city does not belong to the selected region.']);
            }
            $region = $cityRegion;
        }

        if ($region) {
            if ($country && (int) $country->id !== (int) $region->country_id) {
                throw ValidationException::withMessages(['region_id' => 'Selected region does not belong to the selected country.']);
            }
            $country ??= DB::table('countries')->select(['id', 'name'])->where('id', $region->country_id)->first();
        }

        return [
            'country_id' => $country ? (int) $country->id : null,
            'region_id' => $region ? (int) $region->id : null,
            'city_id' => $city ? (int) $city->id : null,
            'label' => $city?->name ?? $region?->name ?? $country?->name,
        ];
    }

    /** @return array<string, list<array<string, int|string|bool>>> */
    public function options(?int $countryId = null, ?int $regionId = null): array
    {
        $countries = DB::table('countries')->select(['id', 'name'])->orderBy('name')->limit(100)->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => mb_substr((string) $row->name, 0, 120)])->all();
        $regions = $countryId ? DB::table('regions')->select(['id', 'country_id', 'name', 'use_for_yandex_direct'])
            ->where('country_id', $countryId)->orderBy('name')->limit(100)->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'country_id' => (int) $row->country_id,
                'name' => mb_substr((string) $row->name, 0, 120),
                'current_service_region' => (bool) $row->use_for_yandex_direct,
            ])->all() : [];
        $cities = $regionId ? DB::table('cities')->select(['id', 'region_id', 'name'])
            ->where('region_id', $regionId)->orderBy('name')->limit(100)->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'region_id' => (int) $row->region_id,
                'name' => mb_substr((string) $row->name, 0, 120),
            ])->all() : [];

        return ['countries' => $countries, 'regions' => $regions, 'cities' => $cities];
    }
}
