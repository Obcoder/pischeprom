<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\YandexDirectGeoRegion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $countryId = $request->input('country_id');
        $direct = $request->input('direct');

        $regions = Region::query()
            ->with('country')
            ->withCount('cities')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('country', fn ($country) => $country->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($countryId), fn ($query) => $query->where('country_id', $countryId))
            ->when($direct === 'mapped', fn ($query) => $query->whereNotNull('yandex_direct_region_ids'))
            ->when($direct === 'active', fn ($query) => $query->where('use_for_yandex_direct', true))
            ->when($direct === 'empty', fn ($query) => $query->whereNull('yandex_direct_region_ids'))
            ->orderBy('country_id')
            ->orderBy('name')
            ->get();

        return response()->json($this->attachYandexGeoRegions($regions));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): Region
    {
        return Region::with('cities')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Region $region): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'country_id' => ['sometimes', 'required', 'integer', 'exists:countries,id'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'yandex_direct_region_ids' => ['nullable', 'array'],
            'yandex_direct_region_ids.*' => ['integer', 'min:1'],
            'use_for_yandex_direct' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('yandex_direct_region_ids', $validated)) {
            $validated['yandex_direct_region_ids'] = collect($validated['yandex_direct_region_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all() ?: null;
        }

        $region->update($validated);

        return response()->json($this->attachYandexGeoRegions(
            new Collection([$region->fresh(['country'])->loadCount('cities')])
        )->first());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function attachYandexGeoRegions(Collection $regions): Collection
    {
        if (!Schema::hasTable('yandex_direct_geo_regions')) {
            return $regions;
        }

        $ids = $regions
            ->flatMap(fn (Region $region) => $region->yandex_direct_region_ids ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $regions;
        }

        $geoRegions = YandexDirectGeoRegion::query()
            ->whereIn('external_region_id', $ids->all())
            ->get()
            ->keyBy('external_region_id');

        return $regions->each(function (Region $region) use ($geoRegions) {
            $region->setAttribute('yandex_direct_geo_regions', collect($region->yandex_direct_region_ids ?: [])
                ->map(function ($id) use ($geoRegions) {
                    $id = (int) $id;
                    $geoRegion = $geoRegions->get($id);

                    return $geoRegion ? [
                        'id' => $geoRegion->id,
                        'external_region_id' => $geoRegion->external_region_id,
                        'parent_id' => $geoRegion->parent_id,
                        'name' => $geoRegion->name,
                        'type' => $geoRegion->type,
                        'parent_names' => $geoRegion->parent_names ?: [],
                        'path' => collect($geoRegion->parent_names ?: [])->push($geoRegion->name)->filter()->implode(' / '),
                        'synced_at' => $geoRegion->synced_at,
                    ] : [
                        'external_region_id' => $id,
                        'name' => null,
                        'parent_names' => [],
                        'path' => null,
                    ];
                })
                ->values()
                ->all());
        });
    }
}
