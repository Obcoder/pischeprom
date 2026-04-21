<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Building;
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
    protected function ok(Unit $unit): JsonResponse
    {
        $unit->load(Unit::DETAIL_RELATIONS);

        return response()->json([
                                    'message' => 'OK',
                                    'unit' => $unit,
                                ]);
    }

    public function attachUri(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'uri_id' => ['required', 'integer', 'exists:uris,id'],
                                        ]);

        $unit->uris()->syncWithoutDetaching([(int) $validated['uri_id']]);

        return $this->ok($unit);
    }

    public function detachUri(Unit $unit, Uri $uri): JsonResponse
    {
        $unit->uris()->detach($uri->id);

        return $this->ok($unit);
    }

    public function attachTelephone(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'telephone_id' => ['required', 'integer', 'exists:telephones,id'],
                                        ]);

        $unit->telephones()->syncWithoutDetaching([(int) $validated['telephone_id']]);

        return $this->ok($unit);
    }

    public function detachTelephone(Unit $unit, Telephone $telephone): JsonResponse
    {
        $unit->telephones()->detach($telephone->id);

        return $this->ok($unit);
    }

    public function attachBuilding(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'building_id' => ['required', 'integer', 'exists:buildings,id'],
                                        ]);

        $unit->buildings()->syncWithoutDetaching([(int) $validated['building_id']]);

        return $this->ok($unit);
    }

    public function detachBuilding(Unit $unit, Building $building): JsonResponse
    {
        $unit->buildings()->detach($building->id);

        return $this->ok($unit);
    }

    public function attachLabel(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'label_id' => ['required', 'integer', 'exists:labels,id'],
                                        ]);

        $unit->labels()->syncWithoutDetaching([(int) $validated['label_id']]);

        return $this->ok($unit);
    }

    public function detachLabel(Unit $unit, Label $label): JsonResponse
    {
        $unit->labels()->detach($label->id);

        return $this->ok($unit);
    }

    public function attachField(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'field_id' => ['required', 'integer', 'exists:fields,id'],
                                        ]);

        $unit->fields()->syncWithoutDetaching([(int) $validated['field_id']]);

        return $this->ok($unit);
    }

    public function detachField(Unit $unit, Field $field): JsonResponse
    {
        $unit->fields()->detach($field->id);

        return $this->ok($unit);
    }

    public function attachEntity(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'entity_id' => ['required', 'integer', 'exists:entities,id'],
                                        ]);

        $unit->entities()->syncWithoutDetaching([(int) $validated['entity_id']]);

        return $this->ok($unit);
    }

    public function detachEntity(Unit $unit, Entity $entity): JsonResponse
    {
        $unit->entities()->detach($entity->id);

        return $this->ok($unit);
    }
}
