<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use Illuminate\Http\Request;

class EntityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $like = $request->search;
        $entities = Entity::with('entityClass')
            ->where(function ($query) use ($like) {
            $query->where('name', 'like', '%' . $like . '%');
        })
            ->orderBy('name')
            ->get();

        return $entities;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $entity = Entity::create($request->all());
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
