<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['goods.quotations'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->category($request->category_id);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $product = Product::create($data);

        return Product::with([
                                 'category',
                                 'manufacturers',
                             ])->findOrFail($product->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with([
                                     'category',
                                     'manufacturers',
                                     'components',
                                     'goods.quotations',
                                     'units',
                                     'consumers.unit.uris',
                                     'consumers.measure',
                                 ])->findOrFail($id);

        $product->setAttribute(
            'sales',
            Sale::query()
                ->with(['entity', 'goods'])
                ->byProduct((int) $id)
                ->orderByDesc('date')
                ->get()
        );

        return $product;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate($this->rules());

        $product = Product::findOrFail($id);
        $product->update($data);

        return $this->show((string) $product->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
                                    'message' => 'Product deleted successfully',
                                ]);
    }

    private function rules(): array
    {
        $rules = [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_published' => ['nullable', 'boolean'],
        ];

        foreach (Product::TRANSLATION_COLUMNS as $column) {
            $rules[$column] = [$column === 'rus' ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
