<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\Wikipedia\WikipediaCityService;

class CityController extends Controller
{
    public function __construct(
        private readonly WikipediaCityService $wikipediaCityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $requestedItemsPerPage = (int) $request->input('itemsPerPage', 25);
        $isAllRequested = $requestedItemsPerPage === -1;

        $itemsPerPage = max(1, min($requestedItemsPerPage, 200));
        $page = max(1, (int) $request->input('page', 1));

        $query = City::query()
            ->forCitiesTable()
            ->withCount([
                            'buildings',
                            'entities',
                            'units',
                        ])
            ->search($request->input('search'))
            ->filterRegion($request->input('region_id'))
            ->filterCountry($request->input('country_id'))
            ->filterUnit($request->input('unit_id'))
            ->filterEntity($request->input('entity_id'))
            ->filterHasBuildings($request->input('has_buildings'));

        $this->applySorting($query, $request);

        if ($isAllRequested) {
            $items = $query->get();

            return response()->json([
                                        'items' => $items,
                                        'total' => $items->count(),
                                        'page' => 1,
                                        'itemsPerPage' => -1,
                                    ]);
        }

        $paginator = $query->paginate(
            perPage: $itemsPerPage,
            columns: ['*'],
            pageName: 'page',
            page: $page,
        );

        return response()->json([
                                    'items' => $paginator->items(),
                                    'total' => $paginator->total(),
                                    'page' => $paginator->currentPage(),
                                    'itemsPerPage' => $paginator->perPage(),
                                ]);
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $populations = $validated['populations'] ?? [];
        unset($validated['populations']);

        $validated = $this->wikipediaCityService->enrichCityData($validated);

        if (empty($validated['wiki_lang'])) {
            $validated['wiki_lang'] = 'ru';
        }

        $city = DB::transaction(function () use ($validated, $populations) {
            $city = City::create($validated);

            $this->syncPopulations($city, $populations);

            return $city;
        });

        $city->load([
                        'region.country',
                        'latestPopulation',
                        'populations',
                    ]);

        return response()->json($city, 201);
    }

    public function show(City $city): JsonResponse
    {
        $city->load([
                        'region.country',
                        'latestPopulation',
                        'populations',
                        'buildings.units',
                        'entities.classification',
                        'units',
                    ]);

        $city->loadCount([
                             'buildings',
                             'entities',
                             'units',
                         ]);

        return response()->json($city);
    }

    public function update(UpdateCityRequest $request, City $city): JsonResponse
    {
        $validated = $request->validated();

        $hasPopulations = array_key_exists('populations', $validated);
        $populations = $validated['populations'] ?? [];

        unset($validated['populations']);

        if (array_key_exists('wiki', $validated)) {
            $validated = $this->wikipediaCityService->enrichCityData($validated);
        }

        if (array_key_exists('wiki_lang', $validated) && empty($validated['wiki_lang'])) {
            $validated['wiki_lang'] = 'ru';
        }

        DB::transaction(function () use ($city, $validated, $hasPopulations, $populations) {
            $city->update($validated);

            if ($hasPopulations) {
                $this->syncPopulations($city, $populations);
            }
        });

        $city->refresh()->load([
                                   'region.country',
                                   'latestPopulation',
                                   'populations',
                               ]);

        return response()->json($city);
    }

    public function destroy(City $city): JsonResponse
    {
        $city->delete();

        return response()->json(null, 204);
    }

    private function syncPopulations(City $city, array $populations): void
    {
        foreach ($populations as $population) {
            if (
                empty($population['year']) ||
                ! array_key_exists('population', $population)
            ) {
                continue;
            }

            $city->populations()->updateOrCreate(
                [
                    'year' => (int) $population['year'],
                ],
                [
                    'population' => (int) $population['population'],
                ],
            );
        }

        $this->syncLegacyPopulation($city);
    }

    private function syncLegacyPopulation(City $city): void
    {
        $latestPopulation = $city->populations()
            ->orderByDesc('year')
            ->first();

        if ($latestPopulation) {
            $city->forceFill([
                                 'population' => $latestPopulation->population,
                             ])->save();
        }
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $this->normalizeSortBy($request->input('sortBy'));

        if (! $sort) {
            $query
                ->orderByLatestPopulation('desc')
                ->orderBy('name');

            return;
        }

        $key = $sort['key'] ?? null;
        $order = strtolower($sort['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        match ($key) {
            'name' => $query->orderBy('name', $order),

            'region.name' => $query->orderBy(
                Region::select('name')
                    ->whereColumn('regions.id', 'cities.region_id')
                    ->limit(1),
                $order
            ),

            'region.country.name',
            'country.name' => $query->orderBy(
                Country::select('countries.name')
                    ->join('regions', 'regions.country_id', '=', 'countries.id')
                    ->whereColumn('regions.id', 'cities.region_id')
                    ->limit(1),
                $order
            ),

            'latest_population.population',
            'latestPopulation.population',
            'population' => $query->orderByLatestPopulation($order),

            'latitude' => $query->orderBy('latitude', $order),
            'longitude' => $query->orderBy('longitude', $order),
            'created_at' => $query->orderBy('created_at', $order),
            'updated_at' => $query->orderBy('updated_at', $order),

            default => $query->orderBy('name'),
        };
    }

    private function normalizeSortBy(mixed $sortBy): ?array
    {
        if (is_string($sortBy)) {
            $decoded = json_decode($sortBy, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $sortBy = $decoded;
            } else {
                return [
                    'key' => $sortBy,
                    'order' => 'asc',
                ];
            }
        }

        if (! is_array($sortBy) || empty($sortBy)) {
            return null;
        }

        if (array_is_list($sortBy)) {
            return $sortBy[0] ?? null;
        }

        return $sortBy;
    }
}
