<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntityController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->get('itemsPerPage', 10));
        $page = (int) ($request->get('page', 1));
        $sortBy = $request->get('sortBy');
        $sortDesc = $request->get('sortDesc');

        $filters = [
            'entity_classification_ids' => $request->input('entity_classification_ids', []),
            'country_ids' => $request->input('country_ids', []),
            'city_ids' => $request->input('city_ids', []),
            'building_ids' => $request->input('building_ids', []),
            'email_ids' => $request->input('email_ids', []),
            'telephone_ids' => $request->input('telephone_ids', []),
            'unit_ids' => $request->input('unit_ids', []),
            'chat_ids' => $request->input('chat_ids', []),
        ];

        $baseQuery = Entity::query()
            ->baseRelations()
            ->withStats()
            ->search($request->get('search'))
            ->filter($filters);

        $paginated = (clone $baseQuery)
            ->applySort($sortBy, $sortDesc)
            ->paginate($perPage, ['*'], 'page', $page);

        $pageMarkers = $this->buildPageMarkers((clone $baseQuery), $perPage, $sortBy, $sortDesc);

        return response()->json([
                                    'data' => $paginated->items(),
                                    'meta' => [
                                        'current_page' => $paginated->currentPage(),
                                        'last_page' => $paginated->lastPage(),
                                        'per_page' => $paginated->perPage(),
                                        'total' => $paginated->total(),
                                        'page_markers' => $pageMarkers,
                                    ],
                                ]);
    }

    protected function buildPageMarkers($query, int $perPage, ?string $sortBy, ?string $sortDesc): array
    {
        $sorted = (clone $query)->applySort($sortBy, $sortDesc);

        $total = (clone $sorted)->count();
        $lastPage = (int) ceil($total / $perPage);

        $markers = [];

        for ($page = 1; $page <= $lastPage; $page++) {
            $firstItem = (clone $sorted)
                ->forPage($page, $perPage)
                ->first();

            $markers[] = [
                'page' => $page,
                'first_name' => $firstItem?->name,
            ];
        }

        return $markers;
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

        return response()->json(
            Entity::query()->baseRelations()->withStats()->findOrFail($entity->id),
            201
        );
    }

    public function show(string $id)
    {
        $entity = Entity::query()
            ->baseRelations()
            ->withStats()
            ->findOrFail($id);

        return response()->json($entity);
    }

    public function update(UpdateEntityRequest $request, string $id)
    {
        $entity = Entity::findOrFail($id);

        DB::transaction(function () use ($request, $entity) {
            $entity->update($request->validated());

            $entity->buildings()->sync($request->input('buildings', []));
            $entity->cities()->sync($request->input('cities', []));
            $entity->emails()->sync($request->input('emails', []));
            $entity->telephones()->sync($request->input('telephones', []));
            $entity->units()->sync($request->input('units', []));
            $entity->chats()->sync($request->input('chats', []));
        });

        return response()->json(
            Entity::query()->baseRelations()->withStats()->findOrFail($entity->id)
        );
    }

    public function destroy(string $id)
    {
        $entity = Entity::findOrFail($id);

        DB::transaction(function () use ($entity) {
            $entity->buildings()->detach();
            $entity->cities()->detach();
            $entity->emails()->detach();
            $entity->telephones()->detach();
            $entity->units()->detach();
            $entity->chats()->detach();
            $entity->delete();
        });

        return response()->json([
                                    'message' => 'Entity deleted successfully',
                                ]);
    }
}
