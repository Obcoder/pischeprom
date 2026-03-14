<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
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
        $data = $request->validate([
                                       'rus' => ['required', 'string', 'max:255'],
                                       'eng' => ['nullable', 'string', 'max:255'],
                                       'zh' => ['nullable', 'string', 'max:255'],
                                       'es' => ['nullable', 'string', 'max:255'],
                                       'ar' => ['nullable', 'string', 'max:255'],
                                       'po' => ['nullable', 'string', 'max:255'],
                                       'de' => ['nullable', 'string', 'max:255'],
                                       'fr' => ['nullable', 'string', 'max:255'],
                                       'hi' => ['nullable', 'string', 'max:255'],
                                       'tu' => ['nullable', 'string', 'max:255'],
                                       'vi' => ['nullable', 'string', 'max:255'],
                                       'it' => ['nullable', 'string', 'max:255'],
                                       'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                                   ]);

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
                                     'consumers.product',
                                     'consumers.unit',
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
        $data = $request->validate([
                                       'rus' => ['required', 'string', 'max:255'],
                                       'eng' => ['nullable', 'string', 'max:255'],
                                       'zh' => ['nullable', 'string', 'max:255'],
                                       'es' => ['nullable', 'string', 'max:255'],
                                       'ar' => ['nullable', 'string', 'max:255'],
                                       'po' => ['nullable', 'string', 'max:255'],
                                       'de' => ['nullable', 'string', 'max:255'],
                                       'fr' => ['nullable', 'string', 'max:255'],
                                       'hi' => ['nullable', 'string', 'max:255'],
                                       'tu' => ['nullable', 'string', 'max:255'],
                                       'vi' => ['nullable', 'string', 'max:255'],
                                       'it' => ['nullable', 'string', 'max:255'],
                                       'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                                   ]);

        $product = Product::create($data);

        return Product::with([
                                 'category',
                                 'manufacturers',
                             ])->findOrFail($product->id);
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
}
