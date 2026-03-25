<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryFormRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
                                            'search' => ['nullable', 'string'],
                                            'sortBy' => ['nullable', 'string', 'in:id,name'],
                                            'sortDesc' => ['nullable', 'boolean'],
                                            'page' => ['nullable', 'integer', 'min:1'],
                                            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                                        ]);

        $perPage = $validated['per_page'] ?? 1115;
        $sortBy = $validated['sortBy'] ?? 'name';
        $sortDirection = !empty($validated['sortDesc']) ? 'desc' : 'asc';

        $categories = Category::query()
            ->withCount('products')
            ->search($validated['search'] ?? null)
            ->ordered($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    public function store(CategoryFormRequest $request): CategoryResource
    {
        $category = DB::transaction(function () use ($request) {
            return Category::create($request->validated());
        });

        return new CategoryResource($category->loadCount('products'));
    }

    public function show(Category $category): CategoryResource
    {
        $category->load(['products'])->loadCount('products');

        return new CategoryResource($category);
    }

    public function update(CategoryFormRequest $request, Category $category): CategoryResource
    {
        DB::transaction(function () use ($request, $category) {
            $category->update($request->validated());
        });

        return new CategoryResource($category->fresh()->loadCount('products'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
                                    'message' => 'Category deleted',
                                ]);
    }
}
