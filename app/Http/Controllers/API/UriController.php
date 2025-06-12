<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Uri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UriController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Uri::with('owners')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $uri = Uri::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
