<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Field;
use App\Models\Good;
use App\Services\Seo\GoodSeoService;
use App\Services\Seo\GoodStructuredDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoodController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $countryId = $request->integer('country_id') ?: null;
        $country = $countryId
            ? Country::query()->select('id', 'name', 'flag')->find($countryId)
            : null;
        $canSeePartnerPrices = $request->user() !== null;

        $goods = Good::query()
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
            ->orderBy('name')
            ->limit(96)
            ->get([
                'id',
                'country_id',
                'name',
                'slug',
                'ava_image',
                'ava_thumb',
                'denominator',
                'description',
                'created_at',
            ]);

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
    ): Response|RedirectResponse {
        $requestedSlug = trim($good);

        $good = Good::query()
            ->with('seo')
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

        return Inertia::render('Goods/Show', [
            'good' => $good,
            'relatedGoods' => $relatedGoods,

            'seo' => [
                'title' => $seoService->title($good),
                'description' => $seoService->description($good),
                'h1' => $seoService->h1($good),
                'canonical' => $seoService->canonical($good),
                'robots' => $seoService->robots($good),
                'image' => $seoService->image($good),
                'category' => $seoService->categoryTitle($good),
                'price' => $seoService->price($good),
                'currency' => $seoService->currency($good),
                'jsonLd' => $structuredDataService->make($good),
                'metricaCounterId' => config('services.yandex_metrica.counter_id'),
            ],
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
