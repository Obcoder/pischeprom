<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Check;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Check::orderBy('date', 'desc')
            ->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Check::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $check = Check::with(['commodities', 'commodities.pivot.measure'])
            ->findOrFail($id);
        return Inertia::render('Ameise/Check', ['check'=>$check]);
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
