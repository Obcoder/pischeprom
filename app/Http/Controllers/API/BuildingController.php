<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $like = $request->search;
        $buildings = Building::where('address', 'like', '%' . $like . '%')
            ->orderBy('created_at', 'desc')
            ->get();
        return $buildings;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Валидация
        $validated = $request->validate([
                                            'city_id' => 'required|exists:cities,id',
                                            'name' => 'required|string|max:255',
                                            'postcode' => 'nullable|string|max:20',
                                        ]);

        // Создание здания и ассоциация с городом
        $building = Building::create($validated);
        $building->city()->associate($request->city_id);
        $building->save();
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
