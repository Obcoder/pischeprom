<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class FieldController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->hasAny(['page', 'per_page', 'search', 'published', 'sort_by', 'sortBy'])) {
            return response()->json(
                Field::query()
                    ->select(['id', 'title', 'slug', 'description', 'is_published', 'sort_order'])
                    ->withCount('goods')
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->get()
            );
        }

        $request->merge([
            'published' => $request->has('published')
                ? filter_var($request->input('published'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'sort_desc' => $request->has('sort_desc')
                ? filter_var($request->input('sort_desc'), FILTER_VALIDATE_BOOLEAN)
                : filter_var($request->input('sortDesc', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'published' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sort_by' => ['nullable', 'string', 'in:id,title,slug,is_published,sort_order,goods_count,categories_count,units_count,updated_at'],
            'sortBy' => ['nullable', 'string', 'in:id,title,slug,is_published,sort_order,goods_count,categories_count,units_count,updated_at'],
            'sort_desc' => ['nullable', 'boolean'],
        ]);

        $sortBy = $validated['sort_by'] ?? $validated['sortBy'] ?? 'sort_order';
        $sortDirection = !empty($validated['sort_desc']) ? 'desc' : 'asc';

        $paginator = Field::query()
            ->withCount(['goods', 'categories', 'units'])
            ->search($validated['search'] ?? null)
            ->when(array_key_exists('published', $validated) && $validated['published'] !== null, function ($query) use ($validated): void {
                $query->where('is_published', $validated['published']);
            })
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('title')
            ->paginate($validated['per_page'] ?? 50)
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Field $field) => $this->payload($field))->values(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $field = Field::create(collect($data)->except('goods')->all());

        if (array_key_exists('goods', $data)) {
            $field->goods()->sync($data['goods'] ?? []);
        }

        return response()->json($this->payload($field->fresh()->load('goods')->loadCount(['goods', 'categories', 'units'])), 201);
    }

    public function show(Field $field): JsonResponse
    {
        return response()->json($this->payload(
            $field->load([
                'goods:id,name,slug,is_published,ava_thumb,ava_image',
            ])->loadCount(['goods', 'categories', 'units'])
        ));
    }

    public function update(Request $request, Field $field): JsonResponse
    {
        $data = $this->validatedData($request, $field);

        $field->update(collect($data)->except('goods')->all());

        if (array_key_exists('goods', $data)) {
            $field->goods()->sync($data['goods'] ?? []);
        }

        return response()->json($this->payload($field->fresh()->load('goods')->loadCount(['goods', 'categories', 'units'])));
    }

    public function destroy(Field $field): JsonResponse
    {
        $field->delete();

        return response()->json(null, 204);
    }

    private function validatedData(Request $request, ?Field $field = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('fields', 'slug')->ignore($field?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'goods' => ['nullable', 'array'],
            'goods.*' => ['integer', 'exists:goods,id'],
        ]);
    }

    private function payload(Field $field): array
    {
        return [
            'id' => $field->id,
            'title' => $field->title,
            'name' => $field->name,
            'slug' => $field->slug,
            'description' => $field->description,
            'is_published' => (bool) $field->is_published,
            'sort_order' => (int) ($field->sort_order ?? 500),
            'goods_count' => (int) ($field->goods_count ?? $field->goods()->count()),
            'categories_count' => (int) ($field->categories_count ?? 0),
            'units_count' => (int) ($field->units_count ?? 0),
            'goods' => $field->relationLoaded('goods')
                ? $field->goods->map(fn ($good) => [
                    'id' => $good->id,
                    'name' => $good->name,
                    'slug' => $good->slug,
                    'is_published' => (bool) $good->is_published,
                    'ava_thumb' => $good->ava_thumb,
                    'ava_image' => $good->ava_image,
                ])->values()
                : [],
            'public_url' => $field->slug
                ? route('public.fields.show', $field->slug, false)
                : null,
            'created_at' => $field->created_at,
            'updated_at' => $field->updated_at,
        ];
    }
}
