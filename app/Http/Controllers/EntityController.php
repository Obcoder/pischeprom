<?php

namespace App\Http\Controllers;

use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;

class EntityController extends Controller
{
    public function index()
    {
        return view('entities.index');
    }

    public function create()
    {
        return view('entities.create');
    }

    public function store(StoreEntityRequest $request)
    {
        DB::transaction(function () use ($request, &$entity) {
            $entity = Entity::create($request->validated());

            $entity->buildings()->sync($request->input('buildings', []));
            $entity->cities()->sync($request->input('cities', []));
            $entity->emails()->sync($request->input('emails', []));
            $entity->telephones()->sync($request->input('telephones', []));
            $entity->units()->sync($request->input('units', []));
            $entity->chats()->sync($request->input('chats', []));
        });

        return redirect()
            ->route('entities.show', $entity)
            ->with('success', 'Entity created');
    }

    public function show(Entity $entity)
    {
        $entity->load([
                          'buildings',
                          'classification',
                          'country',
                          'emails',
                          'telephones',
                          'cities',
                          'chats',
                          'units',
                          'sales',
                      ])->loadCount('sales');

        return view('entities.show', compact('entity'));
    }

    public function edit(Entity $entity)
    {
        $entity->load([
                          'buildings',
                          'classification',
                          'country',
                          'emails',
                          'telephones',
                          'cities',
                          'chats',
                          'units',
                      ]);

        return view('entities.edit', compact('entity'));
    }

    public function update(UpdateEntityRequest $request, Entity $entity)
    {
        DB::transaction(function () use ($request, $entity) {
            $entity->update($request->validated());

            $entity->buildings()->sync($request->input('buildings', []));
            $entity->cities()->sync($request->input('cities', []));
            $entity->emails()->sync($request->input('emails', []));
            $entity->telephones()->sync($request->input('telephones', []));
            $entity->units()->sync($request->input('units', []));
            $entity->chats()->sync($request->input('chats', []));
        });

        return redirect()
            ->route('entities.show', $entity)
            ->with('success', 'Entity updated');
    }

    public function destroy(Entity $entity)
    {
        DB::transaction(function () use ($entity) {
            $entity->buildings()->detach();
            $entity->cities()->detach();
            $entity->emails()->detach();
            $entity->telephones()->detach();
            $entity->units()->detach();
            $entity->chats()->detach();
            $entity->delete();
        });

        return redirect()
            ->route('entities.index')
            ->with('success', 'Entity deleted');
    }
}
