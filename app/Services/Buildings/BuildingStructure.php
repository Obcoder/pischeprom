<?php

namespace App\Services\Buildings;

use App\Models\Building;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildingStructure
{
    public function index(Request $request): array
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:150'], 'city_id' => ['nullable', 'integer', 'min:1'],
            'building_type_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $query = Building::withoutEagerLoads();
        foreach (['city_id', 'building_type_id'] as $field) {
            if (! empty($input[$field])) {
                $query->where($field, $input[$field]);
            }
        }
        $search = trim($input['search'] ?? '');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('address', 'like', '%'.$search.'%')->orWhere('postcode', 'like', '%'.$search.'%')
                ->orWhereHas('city', fn ($city) => $city->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('units', fn ($unit) => $unit->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('apartments', fn ($room) => $room->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('entities', fn ($entity) => $entity->where('name', 'like', '%'.$search.'%'))));
        }
        $ids = (clone $query)->select('buildings.id');
        $summary = [
            'buildings' => (clone $query)->count(),
            'floors' => DB::table('building_floors')->whereIn('building_id', clone $ids)->count(),
            'apartments' => DB::table('apartments')->whereIn('building_id', clone $ids)->count(),
            'without_floor' => DB::table('apartments')->whereIn('building_id', clone $ids)->whereNull('building_floor_id')->count(),
            'entities' => DB::table('apartment_entity')->join('apartments', 'apartments.id', '=', 'apartment_entity.apartment_id')
                ->whereIn('apartments.building_id', clone $ids)->distinct()->count('entity_id'),
        ];
        $page = $this->relations($query)->orderByDesc('created_at')->orderByDesc('id')->paginate($input['per_page'] ?? 50);

        return ['data' => $page->items(), 'summary' => $summary,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]];
    }

    public function show(int $id): Building
    {
        $building = $this->relations(Building::withoutEagerLoads())->findOrFail($id);
        $building->setAttribute('unassigned_entities', DB::table('building_entities as be')->join('entities as e', 'e.id', '=', 'be.entity_id')
            ->where('be.building_id', $id)->whereNull('be.apartment_id')->select(['e.id', 'e.name', 'e.INN'])->distinct()->orderBy('e.name')->get());

        return $building;
    }

    private function relations(Builder $query): Builder
    {
        return $query->with(['city.region', 'buildingType', 'floors',
            'units' => fn ($q) => $q->withoutEagerLoads()->select(['units.id', 'units.name']),
            'apartments.entities' => fn ($q) => $q->withoutEagerLoads()->select(['entities.id', 'entities.name', 'entities.INN']),
        ]);
    }
}
