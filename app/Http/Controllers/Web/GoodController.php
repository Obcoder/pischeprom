<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Inertia\Inertia;
use Inertia\Response;

class GoodController extends Controller
{
    /**
     * Публичная карточка товара
     */
    public function show(Good $good): Response
    {
        abort_unless($good->is_published, 404);

        $good->load([
                        'products.category',

                        'vatRate:id,title,rate',

                        'seo',

                        'publishedMedia.folder',

                        'priceTypeValues' => function ($query) {
                            $query
                                ->where('is_published', true)
                                ->with([
                                           'priceType.currency',
                                           'currency',
                                       ])
                                ->orderByDesc('updated_at');
                        },
                    ]);

        $relatedGoods = Good::query()
            ->where('id', '!=', $good->id)
            ->where('is_published', true)
            ->with([
                       'seo',
                       'publishedMedia' => function ($query) {
                           $query
                               ->where('type', 'image')
                               ->orderByDesc('is_ava')
                               ->orderBy('sort_order')
                               ->orderBy('id');
                       },
                   ])
            ->inRandomOrder()
            ->limit(8)
            ->get([
                      'id',
                      'name',
                      'slug',
                      'ava_thumb',
                      'ava_image',
                      'description',
                  ]);

        return Inertia::render('Goods/Show', [
            'good' => $good,
            'relatedGoods' => $relatedGoods,
        ]);
    }
}
