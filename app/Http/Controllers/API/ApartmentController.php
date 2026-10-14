<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\Building;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApartmentController extends Controller
{
    public function index(Building $building): JsonResponse
    {
        return response()->json($building->apartments);
    }

    public function store(Request $request, Building $building): JsonResponse
    {
        $data = $this->validated($request, $building);
        $apartment = DB::transaction(function () use ($building, $data): Apartment {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $this->assertFloor($building, $data);
            abort_if($building->apartments()->where('number', $data['number'])->where('type', $data['type'])->exists(), 409, 'Помещение с таким номером уже существует.');

            return $building->apartments()->create($data);
        });

        return response()->json($apartment, 201);
    }

    public function show(Building $building, Apartment $apartment): JsonResponse
    {
        abort_unless($apartment->building_id === $building->id, 404);

        return response()->json($apartment);
    }

    public function update(Request $request, Building $building, Apartment $apartment): JsonResponse
    {
        abort_unless($apartment->building_id === $building->id, 404);
        $data = $this->validated($request, $building, $apartment);
        $apartment = DB::transaction(function () use ($building, $apartment, $data): Apartment {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $this->assertFloor($building, $data);
            $locked = Apartment::query()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            abort_if($building->apartments()->where('number', $data['number'])->where('type', $data['type'])->whereKeyNot($locked->id)->exists(), 409, 'Помещение с таким номером уже существует.');
            $locked->fill($data);

            if ($locked->isDirty(['number', 'type'])) {
                DB::table('orders')
                    ->whereIn('id', DB::table('building_order')->select('order_id')->where('apartment_id', $locked->id))
                    ->whereNull('shipped_sale_id')
                    ->where(fn ($query) => $query->whereNotNull('prepared_at')->orWhereNotNull('prepared_fingerprint'))
                    ->update([
                        'prepared_at' => null,
                        'prepared_fingerprint' => null,
                        'prepared_by_user_id' => null,
                        'fulfillment_warehouse_id' => null,
                        'preparation_invalidated_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $locked->save();

            return $locked;
        }, 3);

        return response()->json($apartment);
    }

    public function destroy(Building $building, Apartment $apartment): JsonResponse
    {
        abort_unless($apartment->building_id === $building->id, 404);

        DB::transaction(function () use ($building, $apartment): void {
            Building::withoutEagerLoads()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $locked = Apartment::query()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->entities()->exists(), 409, 'Помещение связано с Entity. Сначала удалите привязки.');
            foreach (['building_order', 'building_unit', 'building_entities'] as $table) {
                abort_if(DB::table($table)->where('apartment_id', $locked->id)->exists(), 409,
                    'Помещение используется в заказе, организации или компании. Сначала удалите его привязки.');
            }
            $locked->delete();
        });

        return response()->json(null, 204);
    }

    private function validated(Request $request, Building $building, ?Apartment $apartment = null): array
    {
        $request->merge([
            'number' => is_string($request->input('number')) ? trim($request->input('number')) : $request->input('number'),
            'type' => $request->input('type', $apartment?->type ?? 'apartment'),
        ]);

        return $request->validate([
            'number' => [
                'bail', 'required', 'string', 'max:50',
                Rule::unique('apartments')->where('building_id', $building->id)
                    ->where('type', is_string($request->input('type')) ? $request->input('type') : '')
                    ->ignore($apartment?->id),
            ],
            'type' => ['bail', 'required', 'string', Rule::in(Apartment::TYPES)],
            'building_floor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('building_floors', 'id')->where('building_id', $building->id)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
    }

    private function assertFloor(Building $building, array $data): void
    {
        if (! empty($data['building_floor_id'])) {
            abort_unless($building->floors()->whereKey($data['building_floor_id'])->exists(), 409, 'Этаж удалён. Обновите список и выберите другой этаж.');
        }
    }
}
