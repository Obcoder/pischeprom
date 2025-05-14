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
        $entities = Entity::with(['telephones', 'chats', 'units']) // связи
        ->withCount('sales') // добавляем количество продаж
        ->where(function ($query) use ($like) {
            $query->where('name', 'like', '%' . $like . '%');
        })
            ->orderBy('sales_count', 'desc') // сортируем по количеству продаж
            ->get();

        return $entities;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $entity = Entity::create($request->all());
        $entity->telephones()->attach($request->telephones);
        $entity->cities()->attach($request->cities);
        $entity->buildings()->attach($request->buildings);
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
