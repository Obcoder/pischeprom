<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Database\Eloquent\Collection
    {
//        $products = DB::table('products')
//            ->select('products.id', 'products.rus')
//            ->get();
        return Product::with('goods')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Product::create($request->all());
//        return redirect()->route('Ameise.products');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with('goods')->findOrFail($id);
        return $product;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
