<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\City;
use App\Models\Entity;
use App\Models\Field;
use App\Models\Label;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitRelationController extends Controller
{
    public function attachUri(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'uri_id' => ['nullable', 'integer', 'exists:uris,id'],
                                       'address' => ['nullable', 'string', 'max:255'],
                                   ]);

        if (empty($data['uri_id']) && blank($data['address'] ?? null)) {
            return response()->json([
                                        'message' => 'URI is required.',
                                        'errors' => [
                                            'address' => ['Select existing URI or type a new one.'],
                                        ],
                                    ], 422);
        }

        $uri = !empty($data['uri_id'])
            ? Uri::findOrFail($data['uri_id'])
            : Uri::firstOrCreate([
                                     'address' => trim($data['address']),
                                 ]);

        $unit->uris()->syncWithoutDetaching([$uri->id]);

        return response()->json([
                                    'message' => 'URI attached.',
                                    'data' => $uri,
                                ]);
    }

    public function detachUri(Unit $unit, Uri $uri): JsonResponse
    {
        $unit->uris()->detach($uri->id);

        return response()->json([
                                    'message' => 'URI detached.',
                                ]);
    }

    public function attachTelephone(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'telephone_id' => ['nullable', 'integer', 'exists:telephones,id'],
                                       'number' => ['nullable', 'string', 'max:255'],
                                   ]);

        if (empty($data['telephone_id']) && blank($data['number'] ?? null)) {
            return response()->json([
                                        'message' => 'Telephone is required.',
                                        'errors' => [
                                            'number' => ['Select existing telephone or type a new one.'],
                                        ],
                                    ], 422);
        }

        $number = blank($data['number'] ?? null)
            ? null
            : preg_replace('/\s+/', '', trim($data['number']));

        $telephone = !empty($data['telephone_id'])
            ? Telephone::findOrFail($data['telephone_id'])
            : Telephone::firstOrCreate([
                                           'number' => $number,
                                       ]);

        $unit->telephones()->syncWithoutDetaching([$telephone->id]);

        return response()->json([
                                    'message' => 'Telephone attached.',
                                    'data' => $telephone,
                                ]);
    }

    public function detachTelephone(Unit $unit, Telephone $telephone): JsonResponse
    {
        $unit->telephones()->detach($telephone->id);

        return response()->json([
                                    'message' => 'Telephone detached.',
                                ]);
    }

    public function attachBuilding(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'building_id' => ['required', 'integer', 'exists:buildings,id'],
                                   ]);

        $building = Building::findOrFail($data['building_id']);

        $unit->buildings()->syncWithoutDetaching([$building->id]);

        return response()->json([
                                    'message' => 'Building attached.',
                                    'data' => $building,
                                ]);
    }

    public function detachBuilding(Unit $unit, Building $building): JsonResponse
    {
        $unit->buildings()->detach($building->id);

        return response()->json([
                                    'message' => 'Building detached.',
                                ]);
    }

    public function attachLabel(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'label_id' => ['nullable', 'integer', 'exists:labels,id'],
                                       'name' => ['nullable', 'string', 'max:255'],
                                   ]);

        if (empty($data['label_id']) && blank($data['name'] ?? null)) {
            return response()->json([
                                        'message' => 'Label is required.',
                                        'errors' => [
                                            'name' => ['Select existing label or type a new one.'],
                                        ],
                                    ], 422);
        }

        $label = !empty($data['label_id'])
            ? Label::findOrFail($data['label_id'])
            : Label::firstOrCreate([
                                       'name' => trim($data['name']),
                                   ]);

        $unit->labels()->syncWithoutDetaching([$label->id]);

        return response()->json([
                                    'message' => 'Label attached.',
                                    'data' => $label,
                                ]);
    }

    public function detachLabel(Unit $unit, Label $label): JsonResponse
    {
        $unit->labels()->detach($label->id);

        return response()->json([
                                    'message' => 'Label detached.',
                                ]);
    }

    public function attachField(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'field_id' => ['nullable', 'integer', 'exists:fields,id'],
                                       'name' => ['nullable', 'string', 'max:255'],
                                   ]);

        if (empty($data['field_id']) && blank($data['name'] ?? null)) {
            return response()->json([
                                        'message' => 'Field is required.',
                                        'errors' => [
                                            'name' => ['Select existing field or type a new one.'],
                                        ],
                                    ], 422);
        }

        $field = !empty($data['field_id'])
            ? Field::findOrFail($data['field_id'])
            : Field::firstOrCreate([
                                       'name' => trim($data['name']),
                                   ]);

        $unit->fields()->syncWithoutDetaching([$field->id]);

        return response()->json([
                                    'message' => 'Field attached.',
                                    'data' => $field,
                                ]);
    }

    public function detachField(Unit $unit, Field $field): JsonResponse
    {
        $unit->fields()->detach($field->id);

        return response()->json([
                                    'message' => 'Field detached.',
                                ]);
    }

    public function attachCity(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'city_id' => ['nullable', 'integer', 'exists:cities,id'],
                                       'name' => ['nullable', 'string', 'max:255'],
                                   ]);

        if (empty($data['city_id']) && blank($data['name'] ?? null)) {
            return response()->json([
                                        'message' => 'City is required.',
                                        'errors' => [
                                            'name' => ['Select existing city or type a new one.'],
                                        ],
                                    ], 422);
        }

        $city = !empty($data['city_id'])
            ? City::findOrFail($data['city_id'])
            : City::firstOrCreate([
                                      'name' => trim($data['name']),
                                  ]);

        $unit->cities()->syncWithoutDetaching([$city->id]);

        return response()->json([
                                    'message' => 'City attached.',
                                    'data' => $city,
                                ]);
    }

    public function detachCity(Unit $unit, City $city): JsonResponse
    {
        $unit->cities()->detach($city->id);

        return response()->json([
                                    'message' => 'City detached.',
                                ]);
    }

    public function attachEntity(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
                                       'entity_id' => ['required', 'integer', 'exists:entities,id'],
                                   ]);

        $entity = Entity::findOrFail($data['entity_id']);

        $unit->entities()->syncWithoutDetaching([$entity->id]);

        return response()->json([
                                    'message' => 'Entity attached.',
                                    'data' => $entity,
                                ]);
    }

    public function detachEntity(Unit $unit, Entity $entity): JsonResponse
    {
        $unit->entities()->detach($entity->id);

        return response()->json([
                                    'message' => 'Entity detached.',
                                ]);
    }
}
