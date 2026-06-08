<?php

namespace App\Services\Goods;

use App\Models\Category;
use App\Models\Good;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

        $classificationIds = $this->classificationIdsForUser($user);
        $entityIds = $user->entities()->pluck('entities.id');
        $orderedGoods = $this->orderedGoods($entityIds);
        $orderedGoodIds = $orderedGoods->pluck('id')->all();
        $recommendedGoods = $this->recommendedGoods($classificationIds, $orderedGoodIds);

        return [
            'authenticated' => true,
            'mode' => 'personal',
            'title' => 'Персональная витрина',
            'subtitle' => 'Ранее заказанные товары и рекомендации по ОКВЭД',
            'table_of_contents' => [],
            'ordered_goods' => $orderedGoods->values(),
            'recommended_goods' => $recommendedGoods->values(),
            'classifications' => $this->classificationPayload($user, $classificationIds),
        ];
    }

    private function tableOfContents(): array
    {
        $categories = Category::query()
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
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
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

    private function classificationIdsForUser(User $user): array
    {
        $user->loadMissing([
            'entityClassifications:id,name',
            'entities:id,entity_classification_id',
            'entities.classification:id,name',
            'entities.additionalClassifications:id,name',
            'entities.units:id',
            'entities.units.classifications:id,name',
        ]);

        return collect()
            ->merge($user->entityClassifications->pluck('id'))
            ->merge($user->entities->pluck('entity_classification_id'))
            ->merge($user->entities->flatMap(fn ($entity) => $entity->additionalClassifications->pluck('id')))
            ->merge($user->entities->flatMap(fn ($entity) => $entity->units->flatMap(fn ($unit) => $unit->classifications->pluck('id'))))
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
            ->with(['products.category', 'latestPrice.currency', 'entityClassifications:id,name'])
            ->withMax(['sales as last_ordered_at' => fn ($query) => $query->whereIn('sales.entity_id', $entityIds)], 'date')
            ->orderByDesc('last_ordered_at')
            ->limit(12)
            ->get();
    }

    private function recommendedGoods(array $classificationIds, array $excludeGoodIds): EloquentCollection
    {
        if (empty($classificationIds)) {
            return new EloquentCollection();
        }

        return Good::query()
            ->where('is_published', true)
            ->when(! empty($excludeGoodIds), fn ($query) => $query->whereNotIn('id', $excludeGoodIds))
            ->whereHas('entityClassifications', fn ($query) => $query->whereIn('entity_classifications.id', $classificationIds))
            ->with(['products.category', 'latestPrice.currency', 'entityClassifications:id,name'])
            ->withCount(['entityClassifications as recommendation_hits' => fn ($query) => $query->whereIn('entity_classifications.id', $classificationIds)])
            ->orderByDesc('recommendation_hits')
            ->orderByDesc('created_at')
            ->limit(18)
            ->get();
    }

    private function classificationPayload(User $user, array $classificationIds): array
    {
        if (empty($classificationIds)) {
            return [];
        }

        return collect()
            ->merge($user->entityClassifications)
            ->merge($user->entities->pluck('classification')->filter())
            ->merge($user->entities->flatMap(fn ($entity) => $entity->additionalClassifications))
            ->merge($user->entities->flatMap(fn ($entity) => $entity->units->flatMap(fn ($unit) => $unit->classifications)))
            ->whereIn('id', $classificationIds)
            ->unique('id')
            ->map(fn ($classification) => [
                'id' => $classification->id,
                'name' => $classification->name,
            ])
            ->values()
            ->all();
    }
}
