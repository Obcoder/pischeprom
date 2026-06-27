<?php

namespace App\Services\Goods;

use App\Models\Category;
use App\Models\Good;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class HomeGoodsModuleService
{
    public function build(?User $user): array
    {
        if (! $user) {
            return [
                'authenticated' => false,
                'mode' => 'catalogue',
                'title' => 'Оглавление товаров',
                'subtitle' => 'Каталог в стиле книги о вкусной и здоровой пище',
                'table_of_contents' => $this->tableOfContents(),
                'ordered_goods' => [],
                'recommended_goods' => [],
                'classifications' => [],
            ];
        }

        $industryIds = $this->industryIdsForUser($user);
        $entityIds = $user->entities()->pluck('entities.id');
        $orderedGoods = $this->orderedGoods($entityIds);
        $orderedGoodIds = $orderedGoods->pluck('id')->all();
        $recommendedGoods = $this->recommendedGoods($industryIds, $orderedGoodIds);

        return [
            'authenticated' => true,
            'mode' => 'personal',
            'title' => 'Персональная витрина',
            'subtitle' => 'Ранее заказанные товары и рекомендации по ОКВЭД',
            'table_of_contents' => [],
            'ordered_goods' => $orderedGoods->values(),
            'recommended_goods' => $recommendedGoods->values(),
            'classifications' => $this->industryPayload($user, $industryIds),
        ];
    }

    private function tableOfContents(): array
    {
        $categoriesQuery = Category::query()
            ->published()
            ->with([
                'products' => fn ($query) => $query
                    ->withCount([
                        'goods as published_goods_count' => fn ($goodsQuery) => $goodsQuery->where('goods.is_published', true),
                    ])
                    ->orderBy('rus')
                    ->limit(14),
            ])
            ->withCount('goods')
            ->when(
                Schema::hasColumn('categories', 'is_featured'),
                fn ($query) => $query->orderByDesc('is_featured')
            )
            ->when(
                Schema::hasColumn('categories', 'sort_order'),
                fn ($query) => $query->orderBy('sort_order')
            )
            ->orderBy('name');

        $categories = $categoriesQuery
            ->limit(10)
            ->get();

        $page = 5;

        return $categories
            ->map(function (Category $category) use (&$page) {
                $entries = $category->products
                    ->filter(fn ($product) => (int) ($product->published_goods_count ?? 0) > 0)
                    ->map(function ($product) use (&$page) {
                        $currentPage = $page;
                        $page += max(1, min(3, (int) ($product->published_goods_count ?? 1)));

                        return [
                            'id' => $product->id,
                            'title' => $product->rus ?: $product->eng ?: "Product #{$product->id}",
                            'page' => $currentPage,
                            'goods_count' => (int) ($product->published_goods_count ?? 0),
                        ];
                    })
                    ->values();

                return [
                    'id' => $category->id,
                    'title' => $category->name,
                    'slug' => $category->slug,
                    'page' => $entries->first()['page'] ?? $page,
                    'entries' => $entries->all(),
                ];
            })
            ->filter(fn (array $section) => ! empty($section['entries']))
            ->values()
            ->all();
    }

    private function industryIdsForUser(User $user): array
    {
        $user->loadMissing([
            'entities.units:id',
            'entities.units.industries:id,code,title',
        ]);

        return collect()
            ->merge($user->entities->flatMap(fn ($entity) => $entity->units->flatMap(fn ($unit) => $unit->industries->pluck('id'))))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function orderedGoods(Collection $entityIds): EloquentCollection
    {
        if ($entityIds->isEmpty()) {
            return new EloquentCollection();
        }

        return Good::query()
            ->where('is_published', true)
            ->whereHas('sales', fn ($query) => $query->whereIn('sales.entity_id', $entityIds))
            ->with([
                'products.category',
                'priceTypeValues.priceType.currency',
                'priceTypeValues.currency',
                'industries:id,code,title',
            ])
            ->withMax(['sales as last_ordered_at' => fn ($query) => $query->whereIn('sales.entity_id', $entityIds)], 'date')
            ->orderByDesc('last_ordered_at')
            ->limit(12)
            ->get();
    }

    private function recommendedGoods(array $industryIds, array $excludeGoodIds): EloquentCollection
    {
        if (empty($industryIds)) {
            return new EloquentCollection();
        }

        return Good::query()
            ->where('is_published', true)
            ->when(! empty($excludeGoodIds), fn ($query) => $query->whereNotIn('id', $excludeGoodIds))
            ->whereHas('industries', fn ($query) => $query->whereIn('industries.id', $industryIds))
            ->with([
                'products.category',
                'priceTypeValues.priceType.currency',
                'priceTypeValues.currency',
                'industries:id,code,title',
            ])
            ->withCount(['industries as recommendation_hits' => fn ($query) => $query->whereIn('industries.id', $industryIds)])
            ->orderByDesc('recommendation_hits')
            ->orderByDesc('created_at')
            ->limit(18)
            ->get();
    }

    private function industryPayload(User $user, array $industryIds): array
    {
        if (empty($industryIds)) {
            return [];
        }

        return $user->entities
            ->flatMap(fn ($entity) => $entity->units->flatMap(fn ($unit) => $unit->industries))
            ->whereIn('id', $industryIds)
            ->unique('id')
            ->map(fn ($industry) => [
                'id' => $industry->id,
                'name' => trim("{$industry->code} {$industry->title}"),
                'code' => $industry->code,
                'title' => $industry->title,
            ])
            ->values()
            ->all();
    }
}
