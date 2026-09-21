<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Field;
use App\Models\Good;
use App\Services\Goods\GoodStockService;
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
    public function index(
        Request $request,
        GoodStockService $stock,
    ): Response {
        $search = trim((string) $request->query('search', ''));
        $countryId = $request->integer('country_id') ?: null;
        $country = $countryId
            ? Country::query()->select('id', 'name', 'flag')->find($countryId)
            : null;
        $canSeePartnerPrices = $request->user() !== null;

        $goods = Good::query()
            ->select([
                'goods.id',
                'goods.country_id',
                'goods.name',
                'goods.slug',
                'goods.ava_image',
                'goods.ava_thumb',
                'goods.denominator',
                'goods.description',
                'goods.created_at',
            ])
            ->where('is_published', true)
            ->when($country, function ($query) use ($country): void {
                $query->where('country_id', $country->id);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('products', function ($productQuery) use ($search): void {
                            $productQuery
                                ->where('rus', 'like', "%{$search}%")
                                ->orWhere('eng', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'seo',
                'stockAvailability',
                'products.category',
                'country:id,name,flag',
                'priceTypeValues' => function ($query) use ($canSeePartnerPrices): void {
                    $query
                        ->where('is_published', true)
                        ->whereHas('priceType', function ($priceTypeQuery) use ($canSeePartnerPrices): void {
                            $priceTypeQuery
                                ->where('is_active', true)
                                ->where(function ($visibleQuery) use ($canSeePartnerPrices): void {
                                    $visibleQuery
                                        ->where('is_public', true)
                                        ->orWhere('code', 'like', '%retail%')
                                        ->orWhere('code', 'like', '%rozn%')
                                        ->orWhere('name', 'like', '%рознич%')
                                        ->orWhere('name', 'like', '%розница%');

                                    if ($canSeePartnerPrices) {
                                        $visibleQuery
                                            ->orWhere('code', 'like', '%partner%')
                                            ->orWhere('code', 'like', '%diler%')
                                            ->orWhere('code', 'like', '%dealer%')
                                            ->orWhere('name', 'like', '%партн%')
                                            ->orWhere('name', 'like', '%дилер%');
                                    }
                                })
                                ->when(! $canSeePartnerPrices, function ($visibleQuery): void {
                                    $visibleQuery
                                        ->where('code', 'not like', '%partner%')
                                        ->where('code', 'not like', '%diler%')
                                        ->where('code', 'not like', '%dealer%')
                                        ->where('name', 'not like', '%партн%')
                                        ->where('name', 'not like', '%дилер%');
                                });
                        })
                        ->with([
                            'priceType.currency',
                            'currency',
                        ])
                        ->orderByDesc('updated_at');
                },
                'publishedMedia' => function ($query): void {
                    $query
                        ->where('type', 'image')
                        ->where('is_published', true)
                        ->orderByDesc('is_ava')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->withExists('stockMovements')
            ->orderBy('name')
            ->limit(96)
            ->get();

        $stock->appendAvailability($goods);

        return Inertia::render('Goods', [
            'goods' => $goods,
            'filters' => [
                'search' => $search,
                'country_id' => $country?->id,
            ],
            'country' => $country,
            'fields' => $this->fieldFilters(),
        ]);
    }

    public function show(
        string $good,
        GoodSeoService $seoService,
        GoodStructuredDataService $structuredDataService,
        GoodStockService $stock,
        PublicGoodOffer $offers,
        MaxMessengerService $max,
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
            ->firstOrFail();

        abort_unless($good->is_published, 404);

        $canonicalSlug = $this->canonicalSlug($good);

        if ($requestedSlug !== $canonicalSlug) {
            return redirect()->route('public.goods.show', [
                'good' => $canonicalSlug,
            ], 301);
        }

        $good->load([
            'products.category',
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

        $relatedGoods = Good::query()
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

    private function canonicalSlug(Good $good): string
    {
        $seo = $good->seo;

        if ($seo?->is_active && filled($seo->slug_override)) {
            return trim($seo->slug_override);
        }

        return $good->slug;
    }

    private function fieldFilters()
    {
        return Field::query()
            ->published()
            ->withCount(['publishedGoods as goods_count'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get([
                'id',
                'title',
                'slug',
                'description',
            ]);
    }
}
