<?php

namespace App\Services\Catalog;

use App\Models\CatalogLanding;
use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\Product;
use App\Services\Goods\GoodAvatarImages;
use App\Services\Goods\GoodStockService;
use App\Services\Goods\PublicGoodOffer;
use App\Services\Seo\GoodSeoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        if (! $product->is_published) {
            return null;
        }
        if ($node = $this->managedNode('product', $product->id)) {
            return $this->catalog->page($node->id);
        }
        $configuration = config('product-pages.pages.'.$product->id);
        if (! is_array($configuration) || empty($configuration['guide'])
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
        $landing = $this->hasLandingTable()
            ? CatalogLanding::where('catalog_node_id', $page['node']['id'])->first(['id', 'catalog_node_id', 'published_content']) : null;
        if ($landing) {
            // An explicitly disabled landing must never reactivate its old PHP preset.
            return $landing->published_content ? $this->fromContent($page, $landing->published_content) : null;
        }
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

        return $this->buildPage($page, $configuration);
    }

    public function hasLandingForProduct(Product $product): bool
    {
        return (bool) config('product-pages.pages.'.$product->id.'.catalog_node_id')
            || $this->managedNode('product', $product->id) !== null;
    }

    /** Draft creation never changes an existing entity's public route. */
    public function managedNode(string $entityType, int $entityId): ?CatalogNode
    {
        if (! $this->hasLandingTable()) {
            return null;
        }

        return CatalogNode::where('entity_type', $entityType)->where('entity_id', $entityId)
            ->whereIn('id', CatalogLanding::whereNotNull('activated_at')->select('catalog_node_id'))
            ->orderBy('id')->first();
    }

    /** Shared discovery for sitemap and verification, including arbitrary catalog levels. */
    public function publishedCatalogPages(): Collection
    {
        $landings = $this->hasLandingTable() ? CatalogLanding::select(['id', 'catalog_node_id', 'published_at'])
            ->selectRaw('published_content IS NOT NULL as has_published')->get()->keyBy('catalog_node_id') : collect();
        $legacyIds = collect(config('product-pages.pages', []))->filter(fn ($value): bool => is_array($value) && filled($value['guide'] ?? null))
            ->pluck('catalog_node_id')->filter();
        $ids = $landings->filter(fn ($landing): bool => (bool) $landing->has_published)->keys()
            ->merge($legacyIds->reject(fn ($id): bool => $landings->has($id)))->unique()->all();

        return $this->catalog->pages($ids)->filter(function (array $page) use ($landings): bool {
            if ($landings->has($page['node']['id'])) {
                return true;
            }
            $node = CatalogNode::find($page['node']['id']);
            $config = $node ? config('product-pages.pages.'.$node->entity_id) : null;

            return $node?->entity_type === 'product' && (int) ($config['catalog_node_id'] ?? 0) === $node->id;
        })->map(function (array $page) use ($landings): array {
            $landing = $landings->get($page['node']['id']);
            $updated = $landing?->published_at ?? CatalogNode::find($page['node']['id'])?->updated_at;

            return [...$page, 'lastmod' => $updated?->toDateString() ?? now()->toDateString()];
        });
    }

    public function fromContent(array $page, array $content, bool $preview = false): array
    {
        $catalog = $content['catalog'] ?? [];
        $configuration = [
            'guide' => $content['template'],
            'source_product_ids' => ($catalog['mode'] ?? 'branch') === 'selection' ? ($catalog['source_product_ids'] ?? []) : [],
            'good_ids' => ($catalog['mode'] ?? 'branch') === 'selection'
                ? ($catalog['good_ids'] ?? []) : $this->catalog->goodsInBranch($page['node']['id'], $preview),
            'inline_good_ids' => $catalog['inline_good_ids'] ?? [],
        ];
        if (! ($catalog['enabled'] ?? true)) {
            $configuration['source_product_ids'] = [];
            $configuration['good_ids'] = [];
        }
        $result = $this->buildPage($page, $configuration);
        $result['content'] = $content;
        $result['children'] = $page['children'];
        $result['preview'] = $preview;
        $result['seo']['h1'] = trim($page['node']['h1'] ?? '')
            ?: trim($content['hero']['title'] ?? '') ?: $page['node']['name'];
        $result['seo']['image'] = $content['hero']['image'] ?? $page['seo']['image'] ?? null;
        if ($preview) {
            $result['seo']['robots'] = 'noindex,nofollow';
        }
        $result['seo']['jsonLd'] = $this->structuredData($result['seo'], $result['goods'], $result['breadcrumbs']);

        return $result;
    }

    private function hasLandingTable(): bool
    {
        return Schema::hasTable('catalog_landings');
    }

    private function buildPage(array $page, array $configuration): array
    {
        $sourceIds = array_values(array_unique(array_map('intval', $configuration['source_product_ids'] ?? [])));
        $goodIds = array_values(array_unique(array_map('intval', $configuration['good_ids'] ?? [])));
        $goods = Good::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereIn('id', $goodIds)
                ->orWhereHas('products', fn ($products) => $products->whereIn('products.id', $sourceIds)->where('products.is_published', true)))
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
            'h1' => $page['seo']['h1'] ?? $page['node']['name'],
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
