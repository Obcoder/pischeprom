<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FieldController extends Controller
{
    public function show(Request $request, string $field): Response|RedirectResponse
    {
        $search = trim((string) $request->query('search', ''));
        $canSeePartnerPrices = $request->user() !== null;

        $fieldModel = Field::query()
            ->published()
            ->where(function ($query) use ($field): void {
                $query->where('slug', $field);

                if (ctype_digit($field)) {
                    $query->orWhere('id', (int) $field);
                }
            })
            ->firstOrFail();

        if ($fieldModel->slug && $field !== $fieldModel->slug) {
            return redirect()->route('public.fields.show', [
                'field' => $fieldModel->slug,
            ], 301);
        }

        $goods = $fieldModel->goods()
            ->where('goods.is_published', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('goods.name', 'like', "%{$search}%")
                        ->orWhere('goods.slug', 'like', "%{$search}%")
                        ->orWhere('goods.description', 'like', "%{$search}%")
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
            ->orderBy('goods.name')
            ->limit(96)
            ->get([
                'goods.id',
                'goods.name',
                'goods.slug',
                'goods.ava_image',
                'goods.ava_thumb',
                'goods.denominator',
                'goods.country_id',
                'goods.description',
                'goods.created_at',
            ]);

        return Inertia::render('Goods', [
            'goods' => $goods,
            'filters' => [
                'search' => $search,
            ],
            'field' => [
                'id' => $fieldModel->id,
                'title' => $fieldModel->title,
                'name' => $fieldModel->name,
                'slug' => $fieldModel->slug,
                'description' => $fieldModel->description,
                'goods_count' => $fieldModel->publishedGoods()->count(),
                'public_url' => route('public.fields.show', $fieldModel->slug),
            ],
            'fields' => $this->fieldFilters(),
        ]);
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
