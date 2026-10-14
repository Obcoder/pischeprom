<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Entity;
use App\Services\Buildings\BuildingStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildingStructureController extends Controller
{
    public function show(Building $building, BuildingStructure $structure): JsonResponse
    {
        return response()->json($structure->show($building->id));
    }

    public function entities(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['search'] ?? '');

        return response()->json(Entity::withoutEagerLoads()->when($search !== '', fn ($query) => $query
            ->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('INN', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name', 'INN']));
    }

    public function attach(Request $request, Building $building, Apartment $apartment): JsonResponse
    {
        abort_unless($apartment->building_id === $building->id, 404);
        $data = $request->validate(['entity_id' => ['required', 'integer', 'exists:entities,id'], 'replace_building_link' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($building, $apartment, $data): void {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $room = $building->apartments()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            Entity::withoutEagerLoads()->whereKey($data['entity_id'])->sharedLock()->firstOrFail();
            $room->entities()->syncWithoutDetaching([$data['entity_id']]);
            if ($data['replace_building_link'] ?? false) {
                DB::table('building_entities')->where('building_id', $building->id)->where('entity_id', $data['entity_id'])->whereNull('apartment_id')->delete();
            }
        });

        return response()->json(['message' => 'Entity привязан к помещению.']);
    }

    public function detach(Building $building, Apartment $apartment, int $entity): JsonResponse
    {
        abort_unless($apartment->building_id === $building->id, 404);
        DB::transaction(function () use ($building, $apartment, $entity): void {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $room = $building->apartments()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            $room->entities()->detach($entity);
            DB::table('building_entities')->where('building_id', $building->id)->where('entity_id', $entity)
                ->where('apartment_id', $room->id)->update(['apartment_id' => null, 'updated_at' => now()]);
        });

        return response()->json(null, 204);
    }
}
