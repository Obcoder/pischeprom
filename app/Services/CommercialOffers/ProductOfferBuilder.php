<?php

namespace App\Services\CommercialOffers;

use App\Models\Category;
use App\Models\Good;
use App\Models\MailingOfferItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class ProductOfferBuilder
{
    public function searchProducts(string $query, array $filters = []): Collection
    {
        return Good::query()
            ->with([
                'products.category',
                'publishedMedia' => fn ($q) => $q->where('type', 'image')->where('is_published', true)->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
                'images' => fn ($q) => $q->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
                'priceTypeValues' => fn ($q) => $q->where('is_published', true)->with(['priceType.currency', 'currency'])->orderByDesc('updated_at'),
            ])
            ->when(trim($query) !== '', function (Builder $goodQuery) use ($query): void {
                $like = "%{$query}%";
                $goodQuery->where(function (Builder $searchQuery) use ($query, $like): void {
                    if (ctype_digit($query)) {
                        $searchQuery->where('id', (int) $query);
                    } else {
                        $searchQuery->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    }

                    $searchQuery->orWhereHas('products', function (Builder $productQuery) use ($like): void {
                        foreach (Product::TRANSLATION_COLUMNS as $column) {
                            $productQuery->orWhere($column, 'like', $like);
                        }

                        $productQuery->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', $like));
                        $productQuery->orWhereHas('manufacturers', fn (Builder $manufacturerQuery) => $manufacturerQuery->where('name', 'like', $like));
                    });
                });
            })
            ->when(array_key_exists('published', $filters), fn ($q) => $q->where('is_published', (bool) $filters['published']))
            ->orderBy('name')
            ->limit((int) ($filters['limit'] ?? 30))
            ->get()
            ->map(fn (Good $good) => $this->productSearchPayload($good));
    }

    public function searchCategories(string $query, array $filters = []): Collection
    {
        return Category::query()
            ->search($query)
            ->when(array_key_exists('published', $filters), fn ($q) => $q->where('is_published', (bool) $filters['published']))
            ->orderBy('name')
            ->limit((int) ($filters['limit'] ?? 30))
            ->get()
            ->map(fn (Category $category) => [
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
            ->with([
                'products.category',
                'publishedMedia' => fn ($q) => $q->where('type', 'image')->where('is_published', true)->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
                'images' => fn ($q) => $q->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
                'priceTypeValues.priceType.currency',
                'priceTypeValues.currency',
            ])
            ->findOrFail($productId);

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
        $category = $product->products->first()?->category ?: $product->products->first();

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
            'category' => $category?->name,
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
}
