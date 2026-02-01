<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryFormRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
                               'search' => 'nullable|string',
                               'sortBy' => 'nullable|string|in:id,name', // Опционально: ограничьте допустимые поля сортировки, чтобы избежать SQL-ошибок
                               'sortDesc' => 'nullable|boolean',
                               'page' => 'nullable|integer|min:1',
                               'per_page' => 'nullable|integer|min:1|max:100',
                           ]);

        $perPage = $request->per_page ?? 25;

        $query = Category::query()
            ->withCount('products')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->sortBy, fn ($q) => $q->orderBy($request->sortBy, $request->sortDesc ? 'desc' : 'asc'));

        $categories = $query->paginate($perPage);

        return CategoryResource::collection($categories);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoryFormRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $category = Category::create($request->validated());
            return new CategoryResource($category);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::with('products')->findOrFail($id);
        return new CategoryResource($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryFormRequest $request, string $id)
    {
        $category = Category::findOrFail($id);

        return DB::transaction(function () use ($request, $category) {
            $category->update($request->validated());
            return new CategoryResource($category);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete(); // Если SoftDeletes, иначе forceDelete()

        return response()->json(['message' => 'Category deleted'], 200);
    }
}
