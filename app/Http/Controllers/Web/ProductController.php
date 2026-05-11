<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Публичная страница Product.
     */
    public function show(Product $product): Response
    {
        $product->load([
                           'category',
                       ]);

        $goods = $product->goods()
            ->where('goods.is_published', true)
            ->with([
                       'seo',
                       'vatRate:id,title,rate',
                       'publishedMedia' => function ($query) {
                           $query
                               ->where('type', 'image')
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

        return Inertia::render('Products/Show', [
            'product' => $product,
            'goods' => $goods,
        ]);
    }
}
