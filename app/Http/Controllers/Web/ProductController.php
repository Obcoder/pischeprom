<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Goods\GoodStockService;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Публичная страница Product.
     */
    public function show(
        Product $product,
        GoodStockService $stock,
    ): Response {
        $product->load([
            'category',
        ]);

        $goods = $product->goods()
            ->select([
                'goods.id',
                'goods.name',
                'goods.slug',
                'goods.ava_image',
                'goods.ava_thumb',
                'goods.description',
            ])
            ->where('goods.is_published', true)
            ->with([
                'seo',
                'stockAvailability',
                'vatRate:id,title,rate',
                'publishedMedia' => function ($query) {
                    $query
                        ->where('type', 'image')
                        ->orderByDesc('is_ava')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->withExists('stockMovements')
            ->orderBy('goods.name')
            ->get();

        $stock->appendAvailability($goods);

        return Inertia::render('Products/Show', [
            'product' => $product,
            'goods' => $goods,
        ]);
    }
}
