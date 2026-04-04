<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Telephone\StoreTelephoneRequest;
use App\Http\Requests\Telephone\UpdateTelephoneRequest;
use App\Models\Entity;
use App\Models\Telephone;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelephoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->integer('page', 1), 1);
        $perPage = max((int) $request->integer('per_page', 10), 1);

        $sortBy = $request->string('sort_by')->toString() ?: 'id';
        $sortOrder = $request->string('sort_order')->toString() ?: 'desc';

        $telephones = Telephone::query()
            ->withRelations()
            ->search($request->string('search')->toString())
            ->filterEntities($request->input('entity_ids', []))
            ->filterUnits($request->input('unit_ids', []))
            ->applySort($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($telephones);
    }

    public function store(StoreTelephoneRequest $request): JsonResponse
    {
        $telephone = Telephone::create([
                                           'number' => $request->string('number')->toString(),
                                       ]);

        $telephone->entities()->sync($request->input('entity_ids', []));
        $telephone->units()->sync($request->input('unit_ids', []));

        $telephone->load([
                             'entities:id,name',
                             'units:id,name',
                         ]);

        return response()->json([
                                    'message' => 'Telephone created successfully.',
                                    'data' => $telephone,
                                ], 201);
    }

    public function show(Telephone $telephone): JsonResponse
    {
        $telephone->load([
                             'entities:id,name',
                             'units:id,name',
                         ]);

        return response()->json([
                                    'data' => $telephone,
                                ]);
    }

    public function update(UpdateTelephoneRequest $request, Telephone $telephone): JsonResponse
    {
        $telephone->update([
                               'number' => $request->string('number')->toString(),
                           ]);

        $telephone->entities()->sync($request->input('entity_ids', []));
        $telephone->units()->sync($request->input('unit_ids', []));

        $telephone->load([
                             'entities:id,name',
                             'units:id,name',
                         ]);

        return response()->json([
                                    'message' => 'Telephone updated successfully.',
                                    'data' => $telephone,
                                ]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
                                    'entities' => Entity::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),
                                    'units' => Unit::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),
                                ]);
    }
}
