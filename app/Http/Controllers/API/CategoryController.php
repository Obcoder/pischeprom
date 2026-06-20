<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryFormRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->merge([
                            'sortDesc' => $request->has('sortDesc')
                                ? filter_var($request->input('sortDesc'), FILTER_VALIDATE_BOOLEAN)
                                : null,
                            'published' => $request->has('published')
                                ? filter_var($request->input('published'), FILTER_VALIDATE_BOOLEAN)
                                : null,
                            'featured' => $request->has('featured')
                                ? filter_var($request->input('featured'), FILTER_VALIDATE_BOOLEAN)
                                : null,
                        ]);

        $validated = $request->validate([
                                            'search' => ['nullable', 'string'],
                                            'published' => ['nullable', 'boolean'],
                                            'featured' => ['nullable', 'boolean'],
                                            'sortBy' => ['nullable', 'string', 'in:id,name,slug,field_id,sort_order,updated_at,products_count,goods_count'],
                                            'sortDesc' => ['nullable', 'boolean'],
                                            'page' => ['nullable', 'integer', 'min:1'],
                                            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
                                        ]);

        $perPage = $validated['per_page'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'sort_order';
        $sortDirection = !empty($validated['sortDesc']) ? 'desc' : 'asc';

        $categories = Category::query()
            ->with('field:id,title')
            ->withCount(['products', 'goods'])
            ->search($validated['search'] ?? null)
            ->when(array_key_exists('published', $validated) && $validated['published'] !== null, function ($query) use ($validated) {
                $query->where('is_published', $validated['published']);
            })
            ->when(array_key_exists('featured', $validated) && $validated['featured'] !== null, function ($query) use ($validated) {
                $query->where('is_featured', $validated['featured']);
            })
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    public function store(CategoryFormRequest $request): CategoryResource
    {
        $category = DB::transaction(function () use ($request) {
            return Category::create($this->categoryPayload($request));
        });

        return new CategoryResource($category->load('field:id,title')->loadCount(['products', 'goods']));
    }

    public function show(Category $category): CategoryResource
    {
        $category
            ->load([
                'field:id,title',
                'products' => fn ($query) => $query->withCount('goods'),
            ])
            ->loadCount(['products', 'goods']);

        return new CategoryResource($category);
    }

    public function update(CategoryFormRequest $request, Category $category): CategoryResource
    {
        DB::transaction(function () use ($request, $category) {
            $category->update($this->categoryPayload($request, $category));
        });

        return new CategoryResource($category->fresh()->load('field:id,title')->loadCount(['products', 'goods']));
    }

    public function destroy(Category $category): JsonResponse
    {
        if (Product::query()->where('category_id', $category->id)->exists()) {
            return response()->json([
                                        'message' => 'Category has linked products and cannot be deleted safely.',
                                    ], 409);
        }

        $this->deleteLocalAvatar($category->image);

        $category->delete();

        return response()->json([
                                    'message' => 'Category deleted',
                                ]);
    }

    private function categoryPayload(CategoryFormRequest $request, ?Category $category = null): array
    {
        $data = collect($request->validated())
            ->except(['avatar', 'remove_avatar'])
            ->all();

        foreach (['is_published', 'is_featured'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->boolean($field);
            }
        }

        if (array_key_exists('keywords', $data)) {
            $data['keywords'] = collect($data['keywords'] ?? [])
                ->map(fn ($keyword) => trim((string) $keyword))
                ->filter()
                ->unique(fn ($keyword) => Str::lower($keyword))
                ->values()
                ->all();
        }

        if ($request->hasFile('avatar')) {
            if ($category) {
                $this->deleteLocalAvatar($category->image);
            }

            $path = $request->file('avatar')->store('category-avatars', 'public');
            $data['image'] = Storage::disk('public')->url($path);
        } elseif ($request->boolean('remove_avatar') && $category) {
            $this->deleteLocalAvatar($category->image);
            $data['image'] = null;
        }

        if (empty($data['published_at']) && ($data['is_published'] ?? $category?->is_published ?? true)) {
            $data['published_at'] = $category?->published_at ?? now();
        }

        if (empty($data['robots'])) {
            $data['robots'] = 'index,follow';
        }

        if (empty($data['h1']) && !empty($data['name'])) {
            $data['h1'] = $data['name'];
        }

        return $data;
    }

    private function deleteLocalAvatar(?string $url): void
    {
        if (!$url) {
            return;
        }

        $storagePath = '/storage/';
        $position = strpos($url, $storagePath);

        if ($position === false) {
            return;
        }

        $path = substr($url, $position + strlen($storagePath));

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
