<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json($regions);
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

        return response()->json($region->fresh(['country'])->loadCount('cities'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
