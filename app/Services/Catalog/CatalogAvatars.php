<?php

namespace App\Services\Catalog;

use App\Models\CatalogNode;
use App\Models\Product;
use Illuminate\Support\Collection;

class CatalogAvatars
{
    public function url(?string $value): ?string
    {
        $value = trim($value ?? '', " \t\n\r\v\f");
        if ($value === '' || preg_match('/[\\\\\x00-\x1f\x7f]/', $value)) {
            return null;
        }
        if (preg_match('~^/(?!/)~', $value)) {
            return $value;
        }
        if (! preg_match('~^https?://~i', $value)) {
            return null;
        }
        $parts = parse_url($value);
        $host = $parts['host'] ?? null;
        if (! $host || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $validHost = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            : CatalogHost::normalize(rawurldecode($host));
        if (! $validHost) {
            return null;
        }

        return $value;
    }

    /** Product avatars belong to the catalog placement, including in legacy product screens. */
    public function appendProducts(Collection $products, bool $public = false): void
    {
        if ($products->isEmpty()) {
            return;
        }
        $ids = $products->pluck('id')->unique();
        $nodes = $public
            ? app(CatalogSiteContext::class)->visibleNodes()->where('entity_type', 'product')->whereIn('entity_id', $ids)
            : CatalogNode::query()->where('entity_type', 'product')->whereIn('entity_id', $ids)
                ->orderBy('id')->get(['id', 'entity_id', 'image']);
        $images = $nodes->sortBy('id')->unique('entity_id')->pluck('image', 'entity_id');
        $products->each(function (Product $product) use ($images): void {
            $image = $this->url($images->get($product->id));
            $product->setAttribute('image', $image);
            $product->setAttribute('thumbnail_url', $image);
        });
    }
}
