<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Models\Building;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Building::query()
            ->with([
                       'city.region.country',
                       'units',
                   ])
            ->orderBy('address');

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->integer('city_id'));
        }

        return response()->json($query->get());
    }

    public function store(StoreBuildingRequest $request): JsonResponse
    {
        $building = Building::create($request->validated());

        $building->load([
                            'city.region.country',
                            'units',
                        ]);

        return response()->json($building, 201);
    }

    public function show(Building $building): JsonResponse
    {
        $building->load([
                            'city.region.country',
                            'units',
                        ]);

        return response()->json($building);
    }

    public function update(UpdateBuildingRequest $request, Building $building): JsonResponse
    {
        $building->update($request->validated());

        $building->refresh()->load([
                                       'city.region.country',
                                       'units',
                                   ]);

        return response()->json($building);
    }

    public function destroy(Building $building): JsonResponse
    {
        $building->delete();

        return response()->json(null, 204);
    }
}
