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
        return response()->json($building->apartments()->create($this->validated($request, $building)), 201);
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
        $apartment = DB::transaction(function () use ($apartment, $data): Apartment {
            $locked = Apartment::query()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
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

        DB::transaction(function () use ($apartment): void {
            $locked = Apartment::query()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
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
        ]);
    }
}
