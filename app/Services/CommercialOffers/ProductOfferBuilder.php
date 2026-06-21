<?php

namespace App\Services\CommercialOffers;

use App\Models\Category;
use App\Models\Good;
use App\Models\MailingOfferItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductOfferBuilder
{
    public function searchProducts(string $query, array $filters = []): Collection
    {
        return Good::query()
            ->with(['products.category', 'publishedMedia', 'priceTypeValues.priceType.currency', 'priceTypeValues.currency'])
            ->search($query)
            ->when(array_key_exists('published', $filters), fn ($q) => $q->where('is_published', (bool) $filters['published']))
            ->limit((int) ($filters['limit'] ?? 30))
            ->get();
    }

    public function searchCategories(string $query, array $filters = []): Collection
    {
        return Category::query()
            ->search($query)
            ->when(array_key_exists('published', $filters), fn ($q) => $q->where('is_published', (bool) $filters['published']))
            ->limit((int) ($filters['limit'] ?? 30))
            ->get();
    }

    public function addProductToCampaign($campaignId, $productId, array $overrides = []): MailingOfferItem
    {
        $good = Good::query()
            ->with(['products.category', 'publishedMedia', 'priceTypeValues.priceType.currency', 'priceTypeValues.currency'])
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
            'thumbnail_url' => $options['thumbnail_url'] ?? $this->absoluteUrl($category->image ?: $category->og_image),
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
        $media = $product->publishedMedia->first() ?: $product->images->first();
        $price = $product->priceTypeValues->first();
        $category = $product->products->first()?->category ?: $product->products->first();

        return [
            'product_id' => $product->id,
            'source_model' => Good::class,
            'sku' => (string) $product->id,
            'title' => $product->name,
            'name' => $product->name,
            'thumbnail_url' => $this->absoluteUrl($media?->thumb_url ?: $media?->url ?: $product->ava_thumb ?: $product->ava_image),
            'price' => $price?->price_gross ?? $price?->price_net,
            'currency' => $price?->currency?->code ?: $price?->priceType?->currency?->code ?: 'RUB',
            'canonical_url' => $this->goodUrl($product),
            'category' => $category?->name,
            'availability' => $product->is_published ? 'published' : 'hidden',
            'description' => Str::limit(strip_tags((string) $product->description), 240),
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
}
