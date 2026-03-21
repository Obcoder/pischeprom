<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryFormRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
                                            'search' => ['nullable', 'string'],
                                            'sortBy' => ['nullable', 'string', 'in:id,name'],
                                            'sortDesc' => ['nullable', 'boolean'],
                                            'page' => ['nullable', 'integer', 'min:1'],
                                            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                                        ]);

        $perPage = $validated['per_page'] ?? 15;
        $sortBy = $validated['sortBy'] ?? 'name';
        $sortDirection = !empty($validated['sortDesc']) ? 'desc' : 'asc';

        $categories = Category::query()
            ->withCount('products')
            ->search($validated['search'] ?? null)
            ->ordered($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Categories', [
            'filters' => [
                'search' => $validated['search'] ?? '',
                'sortBy' => $sortBy,
                'sortDesc' => !empty($validated['sortDesc']),
                'per_page' => $perPage,
            ],
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Categories/Create');
    }

    public function store(CategoryFormRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Категория успешно создана');
    }

    public function show(Category $category)
    {
        $category->load('products')->loadCount('products');

        return Inertia::render('Categories/Show', [
            'category' => new CategoryResource($category),
        ]);
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Categories/Edit', [
            'category' => new CategoryResource($category),
        ]);
    }

    public function update(CategoryFormRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Категория успешно обновлена');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Категория удалена');
    }
}
