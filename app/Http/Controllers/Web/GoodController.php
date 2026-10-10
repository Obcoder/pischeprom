<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodUrlAlias;
use App\Services\Catalog\CatalogSiteContext;
use App\Services\Catalog\PublicGoodsCatalog;
use App\Services\Goods\GoodStockService;
use App\Services\Goods\GoodTradeCodes;
use App\Services\Goods\PublicGoodOffer;
use App\Services\MaxMessengerService;
use App\Services\Seo\GoodSeoService;
use App\Services\Seo\GoodStructuredDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoodController extends Controller
{
    public function index(Request $request, PublicGoodsCatalog $catalog): Response
    {
        return Inertia::render('Goods', $catalog->page($request));
    }

    public function show(
        string $good,
        GoodSeoService $seoService,
        GoodStructuredDataService $structuredDataService,
        GoodStockService $stock,
        PublicGoodOffer $offers,
        MaxMessengerService $max,
        CatalogSiteContext $site,
    ): Response|RedirectResponse {
        $requestedSlug = trim($good);

        $good = Good::query()
            ->with(['seo', 'stockAvailability'])
            ->where(function ($query) use ($requestedSlug) {
                $query
                    ->where('slug', $requestedSlug)
                    ->orWhereHas('seo', function ($seoQuery) use ($requestedSlug) {
                        $seoQuery
                            ->where('is_active', true)
                            ->where('slug_override', $requestedSlug);
                    });
            })
            ->first();

        if (! $good) {
            $alias = GoodUrlAlias::where('slug', $requestedSlug)->first();
            $good = $alias ? Good::with(['seo', 'stockAvailability'])->find($alias->good_id) : null;
        }

        // Existing slugs and SEO aliases take precedence over legacy numeric URLs.
        if (! $good && ctype_digit($requestedSlug)) {
            $good = Good::query()->with(['seo', 'stockAvailability'])->find($requestedSlug);
        }

        abort_unless($good?->is_published && $site->allowsGood($good->id), 404);

        $canonicalSlug = $seoService->publicSlug($good);

        if ($requestedSlug !== $canonicalSlug) {
            return redirect()->route('public.goods.show', [
                'good' => $canonicalSlug,
            ], 301);
        }

        $good->load([
            'products' => fn ($query) => $site->scopeEntities($query, 'product')->where('products.is_published', true),
            'products.category' => fn ($query) => $site->scopeEntities($query, 'category')->where('categories.is_published', true),
            'country:id,name,flag',
            'vatRate:id,title,rate',
            'seo',
            'stockAvailability',
            'publishedMedia' => function ($query) {
                $query
                    ->where('is_published', true)
                    ->orderByDesc('is_ava')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

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

        // Only the public, currently valid offer may reach the landing or its metadata.
        $good->setRelation('priceTypeValues', $offers->pricesFor($good));
        $purchase = $offers->for($good);

        $relatedGoods = $site->scopeGoods(Good::query())
            ->where('id', '!=', $good->id)
            ->where('is_published', true)
            ->with([
                'seo',
                'country:id,name,flag',
                'priceTypeValues.priceType.currency',
                'priceTypeValues.currency',
                'publishedMedia' => function ($query) {
                    $query
                        ->where('type', 'image')
                        ->where('is_published', true)
                        ->orderByDesc('is_ava')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->inRandomOrder()
            ->limit(8)
            ->get([
                'id',
                'country_id',
                'name',
                'slug',
                'ava_thumb',
                'ava_image',
                'description',
            ]);

        $jsonLd = $structuredDataService->make($good, forceGenerate: true);
        foreach ($jsonLd as &$entry) {
            if (($entry['@type'] ?? null) === 'Product') {
                $entry['offers']['priceCurrency'] = $purchase['currency_code'];
                unset($entry['offers']['price'], $entry['offers']['description']);
                if ($purchase['price'] !== null) {
                    $entry['offers']['price'] = number_format($purchase['price'], 2, '.', '');
                } else {
                    $entry['offers']['description'] = 'Цена по запросу';
                }
            }
        }
        unset($entry);

        $pageSeo = [
            'title' => $seoService->title($good),
            'description' => $seoService->description($good),
            'h1' => $seoService->h1($good),
            'canonical' => $seoService->canonical($good),
            'robots' => $seoService->robots($good),
            'image' => $seoService->image($good),
            'category' => $seoService->categoryTitle($good),
            'price' => $purchase['price'],
            'currency' => $purchase['currency_code'],
            'jsonLd' => $jsonLd,
            'metricaCounterId' => config('services.yandex_metrica.counter_id'),
        ];

        // Calculation IDs, margins and staff price comments are not customer data.
        $good->unsetRelation('priceTypeValues');
        $good->makeHidden([
            'incoming_code',
            ...array_diff(GoodTradeCodes::FIELDS, GoodTradeCodes::PUBLIC_FIELDS),
        ]);
        $good->seo?->makeHidden('structured_data');
        foreach ($relatedGoods as $relatedGood) {
            $relatedGood->unsetRelation('priceTypeValues');
            $relatedGood->seo?->makeHidden('structured_data');
        }

        return Inertia::render('Goods/Show', [
            'good' => $good,
            'relatedGoods' => $relatedGoods,
            'availability' => $stock->availabilityPayload($good),
            'publicPurchase' => [...$purchase, 'max_url' => $max->publicProductUrl($good)],
            'seo' => $pageSeo,
        ]);
    }
}
