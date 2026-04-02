<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Http\Resources\EntityResource;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntityController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max((int) $request->integer('itemsPerPage', 10), 1);
        $page = max((int) $request->integer('page', 1), 1);
        $sortBy = $request->string('sortBy')->toString() ?: 'sales_count';
        $sortDesc = filter_var($request->get('sortDesc', true), FILTER_VALIDATE_BOOLEAN);

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
            ->withTableStats()
            ->search($request->get('search'))
            ->filter($filters);

        $paginator = (clone $baseQuery)
            ->applySort($sortBy, $sortDesc)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
                                    'data' => EntityResource::collection($paginator->items()),
                                    'meta' => [
                                        'current_page' => $paginator->currentPage(),
                                        'last_page' => $paginator->lastPage(),
                                        'per_page' => $paginator->perPage(),
                                        'total' => $paginator->total(),
                                        'page_markers' => $this->buildPageMarkers(clone $baseQuery, $perPage, $sortBy, $sortDesc),
                                    ],
                                ]);
    }

    protected function buildPageMarkers($query, int $perPage, string $sortBy, bool $sortDesc): array
    {
        $sortedQuery = (clone $query)->applySort($sortBy, $sortDesc);
        $total = (clone $sortedQuery)->count();
        $lastPage = (int) ceil($total / $perPage);

        $markers = [];

        for ($page = 1; $page <= $lastPage; $page++) {
            $firstItem = (clone $sortedQuery)->forPage($page, $perPage)->first();

            $markers[] = [
                'page' => $page,
                'first_name' => $firstItem?->name,
            ];
        }

        return $markers;
    }

    public function store(StoreEntityRequest $request)
    {
        $entity = DB::transaction(function () use ($request) {
            $entity = Entity::create($request->validated());

            $entity->buildings()->sync($request->input('buildings', []));
            $entity->cities()->sync($request->input('cities', []));
            $entity->emails()->sync($request->input('emails', []));
            $entity->telephones()->sync($request->input('telephones', []));
            $entity->units()->sync($request->input('units', []));
            $entity->chats()->sync($request->input('chats', []));

            return $entity;
        });

        return new EntityResource(
            Entity::query()->baseRelations()->withTableStats()->findOrFail($entity->id)
        );
    }

    public function show(string $id)
    {
        $entity = Entity::query()
            ->baseRelations()
            ->withTableStats()
            ->findOrFail($id);

        return new EntityResource($entity);
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

        return new EntityResource(
            Entity::query()->baseRelations()->withTableStats()->findOrFail($entity->id)
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
