<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Telephone;
use Illuminate\Http\Request;

class TelephoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $like = $request->search;
        $telephones = Telephone::where('number', 'like', "%{$like}%")
            ->with('entities')
            ->orderBy('number', 'desc')
            ->get();
        return $telephones;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Telephone::create($request->all());
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
