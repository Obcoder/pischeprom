<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $like = $request->search;
        $cities = City::where('name', 'like', "%{$like}%")
            ->orderBy('population', 'desc')
            ->get();
        return $cities;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $city = City::create($request->all());
        return redirect()->route('Ameise.cities');
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
