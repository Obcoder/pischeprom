<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\PublicClassPage;
use App\Services\Goods\GoodStockService;
use Illuminate\Http\RedirectResponse;
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
        PublicClassPage $classPages,
    ): Response|RedirectResponse {
        abort_unless($product->is_published, 404);

        if ($classPages->hasLandingForProduct($product)) {
            $target = $classPages->catalogPageForProduct($product);
            abort_unless($target, 404);

            return redirect()->to($target['node']['public_url'], 301);
        }

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
                'goods.measure_id',
                'goods.unit_weight_kg',
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
        $classPage = $classPages->for($product);

        return Inertia::render('Products/Show', [
            'product' => $product,
            'goods' => $goods,
            'classPage' => $classPage,
            'seo' => $classPage['seo'] ?? $classPages->seo($product),
        ]);
    }
}
