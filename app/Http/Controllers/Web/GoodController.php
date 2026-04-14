<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Inertia\Inertia;
use Inertia\Response;

class GoodController extends Controller
{
    /**
     * Страница одного товара
     */
    public function show(Good $good): Response
    {
        abort_unless($good->is_published, 404);

        $good->load([
                        'products:id,name,slug',
                        'prices',
                        'vatRate:id,name,rate',
                    ]);

        $relatedGoods = Good::query()
            ->where('id', '!=', $good->id)
            ->where('is_published', true)
            ->inRandomOrder()
            ->limit(8)
            ->get([
                      'id',
                      'name',
                      'slug',
                      'ava_thumb',
                      'ava_image',
                  ]);

        return Inertia::render('Goods/Show', [
            'good' => $good,
            'relatedGoods' => $relatedGoods,
        ]);
    }
}
