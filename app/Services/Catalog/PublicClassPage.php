<?php

namespace App\Services\Catalog;

use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\Product;
use App\Services\Goods\GoodAvatarImages;
use App\Services\Goods\GoodStockService;
use App\Services\Goods\PublicGoodOffer;
use App\Services\Seo\GoodSeoService;

class PublicClassPage
{
    public function __construct(
        private readonly PublicGoodOffer $offers,
        private readonly GoodStockService $stock,
        private readonly GoodSeoService $goodSeo,
        private readonly GoodAvatarImages $avatars,
        private readonly PublicCatalogService $catalog,
    ) {}

    public function for(Product $product): ?array
    {
        $page = $this->catalogPageForProduct($product);

        return $page ? $this->forCatalogPage($page) : null;
    }

    public function catalogPageForProduct(Product $product): ?array
    {
        $configuration = config('product-pages.pages.'.$product->id);
        if (! $product->is_published || ! is_array($configuration) || empty($configuration['guide'])
            || empty($configuration['catalog_node_id'])) {
            return null;
        }
        $node = CatalogNode::find($configuration['catalog_node_id']);
        if (! $node || $node->entity_type !== 'product' || $node->entity_id !== $product->id) {
            return null;
        }

        // The catalog owns effective publication, including every ancestor.
        return $this->catalog->page($node->id);
    }

    public function forCatalogPage(array $page): ?array
    {
        $productId = collect(config('product-pages.pages', []))->search(fn ($configuration): bool => is_array($configuration) && filled($configuration['guide'] ?? null)
            && (int) ($configuration['catalog_node_id'] ?? 0) === (int) $page['node']['id']);
        if ($productId === false) {
            return null;
        }
        $node = CatalogNode::find($page['node']['id']);
        if (! $node || $node->entity_type !== 'product' || $node->entity_id !== (int) $productId
            || ! Product::query()->whereKey($productId)->where('is_published', true)->exists()) {
            return null;
        }
        $configuration = config('product-pages.pages.'.$productId);

        $sourceIds = array_values(array_unique(array_map('intval', $configuration['source_product_ids'] ?? [])));
        $goods = Good::query()
            ->where('is_published', true)
            ->whereHas('products', fn ($query) => $query->whereIn('products.id', $sourceIds)->where('products.is_published', true))
            ->with([
                'seo', 'stockAvailability', 'country:id,name',
                'publishedMedia' => fn ($query) => $query->where('type', 'image')->reorder()->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
            ])
            ->withExists('stockMovements')
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'slug', 'denominator', 'country_id', 'ava_image', 'ava_thumb', 'is_published']);
        $offers = $this->offers->forMany($goods);
        $cards = $goods->map(function (Good $good) use ($offers): array {
            $image = $good->publishedMedia->first();
            $offer = $offers->get($good->id);
            $attributes = [];
            if ($good->country?->name) {
                $attributes[] = ['label' => 'Страна происхождения', 'value' => $good->country->name];
            }
            if ($offer['package_weight'] !== null) {
                $attributes[] = [
                    'label' => 'Фасовка',
                    'value' => rtrim(rtrim(number_format($offer['package_weight'], 3, ',', ' '), '0'), ',').' кг / упаковка',
                ];
            }

            return [
                'id' => $good->id,
                'name' => $good->name,
                'url' => $this->goodSeo->publicUrl($good),
                'image' => $image?->thumb_url ?: $image?->url ?: $this->avatars->url($good),
                'image_alt' => $image?->alt ?: $good->name,
                'attributes' => $attributes,
                'offer' => $offer,
                'availability' => $this->stock->availabilityPayload($good),
            ];
        });
        // Article references resolve from the same published source selection as
        // the visible cards. Unpublished, deleted or unrelated IDs remain text.
        $inlineGoods = $cards->whereIn('id', $configuration['inline_good_ids'] ?? [])
            ->mapWithKeys(fn (array $card): array => [$card['id'] => array_intersect_key($card, array_flip(['id', 'name', 'url']))]);
        $breadcrumbs = $this->breadcrumbs($page);
        $seo = [
            'title' => $configuration['title'] ?? $page['seo']['title'],
            'description' => $configuration['description'] ?? $page['seo']['description'],
            'h1' => $page['node']['name'],
            'canonical' => $page['node']['public_url'],
            'robots' => 'index,follow',
        ];
        $seo['jsonLd'] = $this->structuredData($seo, $cards->all(), $breadcrumbs);

        return [
            'guide' => $configuration['guide'],
            'goods' => $cards->all(),
            'inlineGoods' => (object) $inlineGoods->all(),
            'seo' => $seo,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    public function seo(Product $product): array
    {
        $configuration = config('product-pages.pages.'.$product->id, []);
        $name = $product->rus ?: $product->eng ?: 'Product #'.$product->id;

        return [
            'title' => $configuration['title'] ?? $name.' — ПИЩЕПРОМ-СЕРВЕР',
            'description' => $configuration['description'] ?? 'Товары по продукту: '.$name,
            'h1' => $name,
            'canonical' => route('shop.products.show', ['product' => $product->id]),
            'robots' => $product->is_published ? 'index,follow' : 'noindex,nofollow',
            'jsonLd' => [],
        ];
    }

    private function breadcrumbs(array $page): array
    {
        $items = [['name' => 'Главная', 'url' => route('home')]];
        foreach ([...$page['breadcrumbs'], $page['node']] as $node) {
            $items[] = ['name' => $node['name'], 'url' => $node['public_url']];
        }

        return $items;
    }

    private function structuredData(array $seo, array $goods, array $breadcrumbs): array
    {
        $list = [
            '@type' => 'ItemList',
            '@id' => $seo['canonical'].'#catalog',
            'numberOfItems' => count($goods),
            'itemListElement' => array_map(fn (array $good, int $index): array => [
                '@type' => 'ListItem', 'position' => $index + 1,
                'name' => $good['name'], 'url' => $good['url'],
            ], $goods, array_keys($goods)),
        ];

        return [
            [
                '@context' => 'https://schema.org', '@type' => 'CollectionPage',
                '@id' => $seo['canonical'], 'url' => $seo['canonical'],
                'name' => $seo['h1'], 'description' => $seo['description'],
                'inLanguage' => 'ru', 'mainEntity' => $list,
            ],
            [
                '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem', 'position' => $index + 1,
                    'name' => $item['name'], 'item' => $item['url'],
                ], $breadcrumbs, array_keys($breadcrumbs)),
            ],
        ];
    }
}
