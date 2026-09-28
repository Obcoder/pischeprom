<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\building_unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuildingUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'apartment_id' => ['sometimes', 'nullable', 'integer', Rule::exists('apartments', 'id')->where('building_id', $request->input('building_id'))],
        ]);

        $buildingUnit = building_unit::query()->updateOrCreate([
            'building_id' => $data['building_id'],
            'unit_id' => $data['unit_id'],
        ], $data);

        return response()->json($buildingUnit, $buildingUnit->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
