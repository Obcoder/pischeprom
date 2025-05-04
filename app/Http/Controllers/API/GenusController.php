<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Genus;
use Illuminate\Http\Request;

class GenusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $genera = Genus::orderBy('name')
            ->get();
        return $genera;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Genus::create($request->all());
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

    public function toggleAgriculturable(Genus $genus)
    {
        $genus->agriculturable = !$genus->agriculturable;
        $genus->save();

        return $genus;
    }

}
