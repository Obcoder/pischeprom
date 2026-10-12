<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckEntityLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'ids' => ['sometimes', 'array', 'max:20'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $query = Entity::query()
            ->setEagerLoads([])
            ->select(['id', 'name', 'full_name', 'INN', 'KPP', 'OGRN', 'entity_classification_id'])
            ->with([
                'classification:id,name',
                'units' => fn ($query) => $query->setEagerLoads([])
                    ->select(['units.id', 'units.name'])
                    ->orderBy('units.name')->orderBy('units.id'),
            ]);

        if (array_key_exists('ids', $validated)) {
            // Selected options must remain available independently of the active search.
            $query->whereIn('id', $validated['ids']);
        } elseif (($search = trim($validated['search'] ?? '')) !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search, 'UTF-8')).'%';

            $query->where(function (Builder $query) use ($pattern): void {
                foreach (['name', 'full_name', 'INN', 'KPP', 'OGRN'] as $column) {
                    $query->orWhereRaw("LOWER(entities.{$column}) LIKE ? ESCAPE '!'", [$pattern]);
                }

                $query->orWhereHas('units', fn (Builder $query) => $query
                    ->whereRaw("LOWER(units.name) LIKE ? ESCAPE '!'", [$pattern]));
            });
        }

        $entities = $query->orderBy('name')->orderBy('id')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => collect($entities->items())->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'full_name' => $entity->full_name,
                'INN' => $entity->INN,
                'KPP' => $entity->KPP,
                'OGRN' => $entity->OGRN,
                'units' => $entity->units->map(fn (Unit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ])->values(),
                'classification' => $entity->classification->exists ? [
                    'id' => $entity->classification->id,
                    'name' => $entity->classification->name,
                ] : null,
            ])->values(),
            'meta' => [
                'current_page' => $entities->currentPage(),
                'last_page' => $entities->lastPage(),
                'per_page' => $entities->perPage(),
                'total' => $entities->total(),
            ],
        ]);
    }
}
