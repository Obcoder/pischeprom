<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BuildingType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuildingTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            BuildingType::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:building_types,name'],
        ]);

        $type = BuildingType::create([
            'name' => trim($data['name']),
        ]);

        return response()->json($type, 201);
    }

    public function show(BuildingType $buildingType): JsonResponse
    {
        return response()->json($buildingType);
    }

    public function update(Request $request, BuildingType $buildingType): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('building_types', 'name')->ignore($buildingType->id),
            ],
        ]);

        $buildingType->update([
            'name' => trim($data['name']),
        ]);

        return response()->json($buildingType->fresh());
    }

    public function destroy(BuildingType $buildingType): JsonResponse
    {
        $buildingType->delete();

        return response()->json(null, 204);
    }
}
