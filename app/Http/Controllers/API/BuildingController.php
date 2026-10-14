<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Models\Building;
use App\Services\Buildings\BuildingStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('structure')) {
            return response()->json(app(BuildingStructure::class)->index($request));
        }
        $query = Building::query()
            ->with([
                'city.region.country',
                'buildingType',
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
            'buildingType',
            'units',
        ]);

        return response()->json($building, 201);
    }

    public function show(Building $building): JsonResponse
    {
        $building->load([
            'city.region.country',
            'buildingType',
            'units',
        ]);

        return response()->json($building);
    }

    public function update(UpdateBuildingRequest $request, Building $building): JsonResponse
    {
        $building->update($request->validated());

        $building->refresh()->load([
            'city.region.country',
            'buildingType',
            'units',
        ]);

        return response()->json($building);
    }

    public function destroy(Building $building): JsonResponse
    {
        DB::transaction(function () use ($building): void {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $apartments = $building->apartments()->lockForUpdate()->pluck('id');
            abort_if(DB::table('apartment_entity')->whereIn('apartment_id', $apartments)->exists(), 409,
                'Помещения здания связаны с Entity. Сначала удалите привязки.');
            foreach (['building_order', 'building_unit', 'building_entities'] as $table) {
                abort_if(DB::table($table)->where(fn ($query) => $query->where('building_id', $building->id)->orWhereIn('apartment_id', $apartments))->exists(), 409,
                    'Здание или его помещения используются в заказах, организациях или компаниях. Сначала удалите привязки.');
            }
            // Delete empty rooms before their floors (the floor FK deliberately restricts deletion).
            $building->apartments()->delete();
            $building->floors()->delete();
            $building->delete();
        });

        return response()->json(null, 204);
    }
}
