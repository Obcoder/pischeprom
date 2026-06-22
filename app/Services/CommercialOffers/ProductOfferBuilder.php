<?php

namespace App\Services\CommercialOffers;

use App\Models\Category;
use App\Models\Good;
use App\Models\MailingOfferItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductOfferBuilder
{
    public function searchProducts(string $query, array $filters = []): LengthAwarePaginator
    {
        return $this->productQuery($query, $filters)
            ->orderBy('name')
            ->paginate($this->perPage($filters))
            ->through(fn (Good $good) => $this->productSearchPayload($good));
    }

    public function searchCategories(string $query, array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->perPage($filters);

        return Category::query()
            ->search($query)
            ->when($this->publishedFilter($filters) !== null, fn ($q) => $q->where('is_published', $this->publishedFilter($filters)))
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Category $category) => [
                'id' => $category->id,
                'title' => $category->name,
                'name' => $category->name,
                'canonical_url' => $this->categoryUrl($category),
                'source_table' => 'categories',
                'source_model' => Category::class,
            ]);
    }

    public function addProductToCampaign($campaignId, $productId, array $overrides = []): MailingOfferItem
    {
        $good = Good::query()
            ->with($this->productRelations())
            ->findOrFail($productId);

        return $this->createProductOfferItem($campaignId, $good, $overrides);
    }

    public function addProductsFromSearchToCampaign($campaignId, string $query, array $filters = []): array
    {
        $matchedIds = $this->productQuery($query, $filters)
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingIds = MailingOfferItem::query()
            ->where('campaign_id', $campaignId)
            ->where('item_type', 'product')
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingLookup = array_fill_keys($existingIds, true);
        $idsToAdd = array_values(array_filter($matchedIds, fn (int $id) => ! isset($existingLookup[$id])));
        $nextSortOrder = (int) MailingOfferItem::query()->where('campaign_id', $campaignId)->max('sort_order');
        $items = [];

        foreach (array_chunk($idsToAdd, 100) as $chunk) {
            $goods = Good::query()
                ->with($this->productRelations())
                ->whereIn('id', $chunk)
                ->get()
                ->keyBy('id');

            foreach ($chunk as $id) {
                $good = $goods->get($id);
                if (! $good instanceof Good) {
                    continue;
                }

                $items[] = $this->createProductOfferItem($campaignId, $good, [
                    'sort_order' => ++$nextSortOrder,
                ]);
            }
        }

        return [
            'matched' => count($matchedIds),
            'added' => count($items),
            'skipped_existing' => count($matchedIds) - count($idsToAdd),
            'items' => $items,
        ];
    }

    public function addCategoryToCampaign($campaignId, $categoryId, array $options = []): MailingOfferItem
    {
        $category = Category::query()->findOrFail($categoryId);

        return MailingOfferItem::query()->create([
            'campaign_id' => $campaignId,
            'category_id' => $category->id,
            'item_type' => 'category',
            'title' => $options['title'] ?? $category->name,
            'thumbnail_url' => $options['thumbnail_url'] ?? $this->safeImageUrl($category->image ?: $category->og_image),
            'canonical_url' => $options['canonical_url'] ?? $this->categoryUrl($category),
            'description' => $options['description'] ?? ($category->short_description ?: $category->description),
            'sort_order' => $options['sort_order'] ?? 0,
            'snapshot' => [
                'category_id' => $category->id,
                'title' => $category->name,
                'canonical_url' => $this->categoryUrl($category),
            ],
        ]);
    }

    public function createProductSnapshot($product): array
    {
        /** @var Good $product */
        $media = $product->publishedMedia
            ->where('type', 'image')
            ->first()
            ?: $product->images->where('type', 'image')->first();
        $price = $product->priceTypeValues->first();
        $linkedProduct = $product->products->first();
        $category = $linkedProduct?->category;
        $vatRate = $product->vatRate?->rate ?? $price?->vat_rate;

        return [
            'product_id' => $product->id,
            'source_model' => Good::class,
            'sku' => (string) $product->id,
            'title' => $product->name,
            'name' => $product->name,
            'thumbnail_url' => $this->safeImageUrl($media?->thumb_url ?: $media?->url ?: $product->ava_thumb ?: $product->ava_image),
            'price' => $price?->price_gross ?? $price?->price_net,
            'currency' => $price?->currency?->code ?: $price?->priceType?->currency?->code ?: 'RUB',
            'canonical_url' => $this->goodUrl($product),
            'category_id' => $category?->id,
            'category' => $category?->name,
            'category_url' => $category ? $this->categoryUrl($category) : null,
            'vat_rate_id' => $product->vatRate?->id,
            'vat_rate_title' => $product->vatRate?->title,
            'vat_rate' => $vatRate !== null ? (float) $vatRate : null,
            'availability' => $product->is_published ? 'published' : 'hidden',
            'description' => Str::limit(strip_tags((string) $product->description), 240),
        ];
    }

    private function productSearchPayload(Good $good): array
    {
        $snapshot = $this->createProductSnapshot($good);

        return $snapshot + [
            'id' => $good->id,
            'source_table' => 'goods',
            'is_published' => (bool) $good->is_published,
            'price_formatted' => $snapshot['price'] !== null ? number_format((float) $snapshot['price'], 2, ',', ' ').' '.$snapshot['currency'] : '-',
        ];
    }

    private function createProductOfferItem($campaignId, Good $good, array $overrides = []): MailingOfferItem
    {
        $snapshot = $this->createProductSnapshot($good);

        return MailingOfferItem::query()->create([
            'campaign_id' => $campaignId,
            'product_id' => $good->id,
            'item_type' => 'product',
            'title' => $overrides['title'] ?? $snapshot['title'],
            'sku' => $overrides['sku'] ?? $snapshot['sku'],
            'thumbnail_url' => $overrides['thumbnail_url'] ?? $snapshot['thumbnail_url'],
            'canonical_url' => $overrides['canonical_url'] ?? $snapshot['canonical_url'],
            'original_price' => $snapshot['price'],
            'offer_price' => $overrides['offer_price'] ?? $snapshot['price'],
            'currency' => $overrides['currency'] ?? $snapshot['currency'],
            'description' => $overrides['description'] ?? $snapshot['description'],
            'sort_order' => $overrides['sort_order'] ?? 0,
            'snapshot' => $snapshot,
        ]);
    }

    private function productQuery(string $query, array $filters = []): Builder
    {
        $search = trim($query);

        return Good::query()
            ->with($this->productRelations())
            ->when($search !== '', function (Builder $goodQuery) use ($search): void {
                $like = "%{$search}%";
                $goodQuery->where(function (Builder $searchQuery) use ($search, $like): void {
                    if (ctype_digit($search)) {
                        $searchQuery->where('id', (int) $search);
                    } else {
                        $searchQuery->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    }

                    $searchQuery->orWhereHas('products', function (Builder $productQuery) use ($like): void {
                        $productQuery->where(function (Builder $translationQuery) use ($like): void {
                            foreach (Product::TRANSLATION_COLUMNS as $column) {
                                $translationQuery->orWhere($column, 'like', $like);
                            }
                        });

                        $productQuery->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', $like));
                        $productQuery->orWhereHas('manufacturers', fn (Builder $manufacturerQuery) => $manufacturerQuery->where('name', 'like', $like));
                    });
                });
            })
            ->when($this->publishedFilter($filters) !== null, fn ($q) => $q->where('is_published', $this->publishedFilter($filters)));
    }

    private function productRelations(): array
    {
        return [
            'products.category',
            'vatRate',
            'publishedMedia' => fn ($q) => $q->where('type', 'image')->where('is_published', true)->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
            'images' => fn ($q) => $q->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
            'priceTypeValues' => fn ($q) => $q->where('is_published', true)->with(['priceType.currency', 'currency'])->orderByDesc('updated_at'),
        ];
    }

    public function updateOfferItemPrice($offerItemId, $price): MailingOfferItem
    {
        $item = MailingOfferItem::query()->findOrFail($offerItemId);
        $item->update(['offer_price' => $price]);

        return $item->fresh();
    }

    public function updateOfferItemCanonicalUrl($offerItemId, $url): MailingOfferItem
    {
        $item = MailingOfferItem::query()->findOrFail($offerItemId);
        $item->update(['canonical_url' => $url]);

        return $item->fresh();
    }

    public function reorderItems($campaignId, array $itemIds): void
    {
        foreach (array_values($itemIds) as $index => $id) {
            MailingOfferItem::query()
                ->where('campaign_id', $campaignId)
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    private function goodUrl(Good $good): string
    {
        try {
            return route('public.goods.show', ['good' => $good->slug], true);
        } catch (\Throwable) {
            return rtrim((string) config('app.url'), '/').'/g/'.$good->slug;
        }
    }

    private function categoryUrl(Category $category): string
    {
        try {
            return route('category.show', ['category' => $category->slug ?: $category->id], true);
        } catch (\Throwable) {
            return rtrim((string) config('app.url'), '/').'/категория/'.($category->slug ?: $category->id);
        }
    }

    private function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    private function safeImageUrl(?string $url): ?string
    {
        $rawUrl = trim((string) $url);
        $lowerRawUrl = Str::lower($rawUrl);
        if ($rawUrl === '' || str_starts_with($lowerRawUrl, 'data:')) {
            return null;
        }

        $url = $this->absoluteUrl($url);
        if ($url === null) {
            return null;
        }

        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if (preg_match('/\.(mp4|m4v|mov|webm|avi|mkv|mpeg|mpg|ogv|m3u8|pdf|doc|docx|xls|xlsx|zip|rar)(?:$|\?)/i', $path)) {
            return null;
        }

        return $url;
    }

    private function perPage(array $filters): int
    {
        return min(100, max(10, (int) ($filters['per_page'] ?? 50)));
    }

    private function publishedFilter(array $filters): ?bool
    {
        if (! array_key_exists('published', $filters) || $filters['published'] === '' || $filters['published'] === null) {
            return null;
        }

        return filter_var($filters['published'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
