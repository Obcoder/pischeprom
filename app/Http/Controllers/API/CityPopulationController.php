<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityPopulationRequest;
use App\Http\Requests\UpdateCityPopulationRequest;
use App\Models\City;
use App\Models\CityPopulation;
use Illuminate\Http\JsonResponse;

class CityPopulationController extends Controller
{
    public function index(City $city): JsonResponse
    {
        return response()->json(
            $city->populations()
                ->orderByDesc('year')
                ->get()
        );
    }

    public function store(StoreCityPopulationRequest $request, City $city): JsonResponse
    {
        $population = $city->populations()->create($request->validated());

        $this->syncLegacyPopulation($city);

        return response()->json($population, 201);
    }

    public function update(
        UpdateCityPopulationRequest $request,
        City $city,
        CityPopulation $cityPopulation,
    ): JsonResponse {
        abort_unless($cityPopulation->city_id === $city->id, 404);

        $cityPopulation->update($request->validated());

        $this->syncLegacyPopulation($city);

        return response()->json($cityPopulation->refresh());
    }

    public function destroy(City $city, CityPopulation $cityPopulation): JsonResponse
    {
        abort_unless($cityPopulation->city_id === $city->id, 404);

        $cityPopulation->delete();

        $this->syncLegacyPopulation($city);

        return response()->json(null, 204);
    }

    private function syncLegacyPopulation(City $city): void
    {
        $latestPopulation = $city->populations()
            ->orderByDesc('year')
            ->first();

        $city->forceFill([
                             'population' => $latestPopulation?->population,
                         ])->save();
    }
}
