<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingFloor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BuildingFloorController extends Controller
{
    public function index(Building $building): JsonResponse
    {
        return response()->json($building->floors()->withCount('apartments')->get());
    }

    public function store(Request $request, Building $building): JsonResponse
    {
        $data = $this->validated($request, $building);
        $floor = DB::transaction(function () use ($building, $data) {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            abort_if($building->floors()->where('number', $data['number'])->exists(), 409, 'Такой этаж уже существует. Обновите список.');

            return $building->floors()->create($data);
        });

        return response()->json($floor, 201);
    }

    public function update(Request $request, Building $building, BuildingFloor $floor): JsonResponse
    {
        abort_unless($floor->building_id === $building->id, 404);
        $data = $this->validated($request, $building, $floor);
        $result = DB::transaction(function () use ($building, $floor, $data) {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $locked = $building->floors()->whereKey($floor->id)->lockForUpdate()->firstOrFail();
            abort_if($building->floors()->where('number', $data['number'])->whereKeyNot($floor->id)->exists(), 409, 'Такой этаж уже существует.');
            $locked->update($data);

            return $locked;
        });

        return response()->json($result);
    }

    public function destroy(Building $building, BuildingFloor $floor): JsonResponse
    {
        abort_unless($floor->building_id === $building->id, 404);
        DB::transaction(function () use ($building, $floor): void {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $locked = $building->floors()->whereKey($floor->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->apartments()->exists(), 409, 'Сначала перенесите помещения на другой этаж или в группу «Без этажа».');
            $locked->delete();
        });

        return response()->json(null, 204);
    }

    private function validated(Request $request, Building $building, ?BuildingFloor $floor = null): array
    {
        return $request->validate([
            'number' => ['required', 'integer', 'between:-20,300', Rule::unique('building_floors')->where('building_id', $building->id)->ignore($floor?->id)],
            'name' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
