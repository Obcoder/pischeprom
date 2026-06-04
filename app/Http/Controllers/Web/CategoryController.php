<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryFormRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Good;
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

    public function show(string $category): Response|RedirectResponse
    {
        $category = Category::query()
            ->where('slug', $category)
            ->orWhere(function ($query) use ($category): void {
                if (ctype_digit($category)) {
                    $query->whereKey((int) $category);
                }
            })
            ->firstOrFail();

        if (!$category->is_published) {
            abort(404);
        }

        if ($category->slug && request()->route('category') !== $category->slug) {
            return redirect()->route('category.show', [
                'category' => $category->slug,
            ], 301);
        }

        $category
            ->load(['products' => function ($query): void {
                $query
                    ->withCount(['goods' => fn ($goodsQuery) => $goodsQuery->where('goods.is_published', true)])
                    ->orderBy('rus');
            }])
            ->loadCount(['products', 'goods']);

        $goods = Good::query()
            ->where('goods.is_published', true)
            ->whereHas('products', fn ($query) => $query->where('products.category_id', $category->id))
            ->with([
                'seo',
                'latestPrice.currency',
                'products:id,rus,category_id',
                'publishedMedia' => function ($query): void {
                    $query
                        ->where('type', 'image')
                        ->where('is_published', true)
                        ->orderByDesc('is_ava')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('goods.name')
            ->get([
                'goods.id',
                'goods.name',
                'goods.slug',
                'goods.ava_image',
                'goods.ava_thumb',
                'goods.description',
            ]);

        $relatedCategories = Category::query()
            ->published()
            ->whereKeyNot($category->id)
            ->withCount(['products', 'goods'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return Inertia::render('Categories/Show', [
            'category' => (new CategoryResource($category))->resolve(),
            'goods' => $goods,
            'relatedCategories' => CategoryResource::collection($relatedCategories)->resolve(),
            'seo' => $this->seoPayload($category),
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

    private function seoPayload(Category $category): array
    {
        $title = $category->meta_title
            ?: "{$category->name} - товары и предложения пищевой промышленности";

        $description = $category->meta_description
            ?: $category->short_description
                ?: "Категория {$category->name}: товары, поставщики, предложения и материалы для пищевой промышленности.";

        $canonical = $category->canonical_url
            ?: route('category.show', ['category' => $category->slug ?: $category->id]);

        return [
            'title' => $title,
            'description' => $description,
            'h1' => $category->h1 ?: $category->name,
            'canonical' => $canonical,
            'robots' => $category->robots ?: 'index,follow',
            'image' => $category->og_image ?: $category->image,
            'ogTitle' => $category->og_title ?: $title,
            'ogDescription' => $category->og_description ?: $description,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->h1 ?: $category->name,
                'description' => $description,
                'url' => $canonical,
            ],
        ];
    }
}
