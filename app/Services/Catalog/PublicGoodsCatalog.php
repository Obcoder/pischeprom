<?php

namespace App\Services\Catalog;

use App\Models\Country;
use App\Models\Field;
use App\Models\Good;
use App\Services\Goods\GoodStockService;
use App\Services\Goods\PublicGoodOffer;
use App\Services\Seo\GoodSeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** The database paginates products; only customer-facing fields leave this service. */
class PublicGoodsCatalog
{
    public function __construct(
        private CatalogSiteContext $site,
        private PublicGoodOffer $offers,
        private GoodStockService $stock,
        private GoodSeoService $seo,
    ) {}

    public function page(Request $request, ?Field $field = null): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'country_id' => ['nullable', 'integer', 'min:1'],
            'field_id' => ['nullable', 'integer', 'min:1'],
            'node_id' => ['nullable', 'integer', 'min:1'],
            'availability' => ['nullable', Rule::in(['all', 'in_stock', 'on_request', 'out_of_stock', 'preorder'])],
            'sort' => ['nullable', Rule::in(['name', 'newest', 'price_asc', 'price_desc'])],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:1000000000000', Rule::when($request->filled('price_min'), 'gte:price_min')],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'view' => ['nullable', Rule::in(['cards', 'tree'])],
            'show_filters' => ['nullable', 'boolean'],
        ]);
        $filters = [
            'search' => trim($data['search'] ?? ''),
            'country_id' => isset($data['country_id']) ? (int) $data['country_id'] : null,
            'field_id' => $field?->id ?? (isset($data['field_id']) ? (int) $data['field_id'] : null),
            'node_id' => isset($data['node_id']) ? (int) $data['node_id'] : null,
            'availability' => $data['availability'] ?? 'all',
            'sort' => $data['sort'] ?? 'name',
            'price_min' => isset($data['price_min']) ? (float) $data['price_min'] : null,
            'price_max' => isset($data['price_max']) ? (float) $data['price_max'] : null,
            'per_page' => (int) ($data['per_page'] ?? 24),
            'view' => $data['view'] ?? 'cards',
            'show_filters' => $request->boolean('show_filters', true),
        ];
        $nodes = $this->site->visibleNodes()->keyBy('id');
        $sections = $nodes->where('entity_type', '!=', 'good');
        if ($filters['node_id']) {
            abort_unless($sections->has($filters['node_id']), 404);
        }
        if ($filters['field_id']) {
            abort_unless(Field::published()->whereKey($filters['field_id'])->exists(), 404);
        }
        $base = $this->site->scopeGoods(Good::query())->where('goods.is_published', true);
        $countries = Country::query()->whereIn('id', (clone $base)->select('country_id')->whereNotNull('country_id'))
            ->orderBy('name')->get(['id', 'name', 'flag']);
        $fields = Field::published()->withCount(['publishedGoods as goods_count' => fn (Builder $query) => $this->site->scopeGoods($query)])
            ->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'slug', 'description'])
            ->filter(fn (Field $item): bool => $item->goods_count > 0)->values();

        $query = (clone $base)
            ->when($filters['country_id'], fn (Builder $q, int $id) => $q->where('country_id', $id))
            ->when($filters['field_id'], fn (Builder $q, int $id) => $q->whereHas('fields', fn (Builder $f) => $f->where('fields.id', $id)->published()))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                // LOWER gives consistent Cyrillic search on MySQL and the SQLite test database.
                $term = '%'.mb_strtolower($filters['search']).'%';
                $query->where(fn (Builder $q) => $q->whereRaw('LOWER(goods.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(goods.slug) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(goods.description) LIKE ?', [$term])
                    ->orWhereHas('products', fn (Builder $p) => $this->site->scopeEntities($p, 'product')->where('products.is_published', true)
                        ->where(fn (Builder $names) => $names->whereRaw('LOWER(rus) LIKE ?', [$term])->orWhereRaw('LOWER(eng) LIKE ?', [$term]))));
            });
        if ($filters['availability'] !== 'all') {
            $query->whereRaw('('.$this->availabilitySql().') = ?', [$filters['availability']]);
        }
        $price = $this->offers->catalogPriceQuery();
        if ($filters['price_min'] !== null) {
            $query->whereRaw('('.$price->toSql().') >= CAST(? AS DECIMAL(18, 4))', [...$price->getBindings(), $filters['price_min']]);
        }
        if ($filters['price_max'] !== null) {
            $query->whereRaw('('.$price->toSql().') <= CAST(? AS DECIMAL(18, 4))', [...$price->getBindings(), $filters['price_max']]);
        }
        // Counts describe the complete filtered result, not just the current page.
        $matchingIds = (clone $query)->pluck('goods.id')->all();
        $tree = $this->tree($sections, $nodes, $matchingIds);
        if ($filters['node_id']) {
            $branchIds = $this->descendants($nodes, $filters['node_id']);
            $query->whereIn('goods.id', $nodes->only($branchIds)->where('entity_type', 'good')->pluck('entity_id')->unique()->all());
        }
        $query->select([
            'goods.id', 'goods.country_id', 'goods.name', 'goods.slug', 'goods.ava_image', 'goods.ava_thumb',
            'goods.denominator', 'goods.measure_id', 'goods.unit_weight_kg', 'goods.description', 'goods.created_at',
        ])->with([
            'measure:id,name', 'country:id,name,flag',
            'seo:id,good_id,slug_override,is_active,availability_status,min_order',
            'stockAvailability',
            'publishedMedia' => fn ($q) => $q->where('type', 'image')->select('id', 'good_id', 'type', 'url', 'thumb_url', 'alt', 'title', 'sort_order', 'is_ava')->orderByDesc('is_ava')->orderBy('sort_order')->orderBy('id'),
        ])->withExists('stockMovements');
        if (str_starts_with($filters['sort'], 'price_')) {
            $query->selectSub($price, 'catalog_price')->orderByRaw('catalog_price IS NULL')
                ->orderBy('catalog_price', $filters['sort'] === 'price_desc' ? 'desc' : 'asc');
        } elseif ($filters['sort'] === 'newest') {
            $query->orderByDesc('goods.created_at');
        }
        $query->orderBy('goods.name')->orderBy('goods.id');
        $goods = $query->paginate($filters['per_page'])->withQueryString();
        if ($goods->currentPage() > $goods->lastPage()) {
            $goods = $query->paginate($filters['per_page'], ['*'], 'page', $goods->lastPage())->withQueryString();
        }
        $this->stock->appendAvailability($goods->getCollection());
        $offers = $this->offers->forMany($goods->getCollection());
        $placements = $nodes->where('entity_type', 'good')->groupBy('entity_id');
        $goods->through(function (Good $good) use ($offers, $placements, $sections, $filters, $nodes): array {
            $options = $placements->get($good->id, collect());
            if ($filters['node_id']) {
                $inBranch = array_fill_keys($this->descendants($nodes, $filters['node_id']), true);
                $options = $options->filter(fn (array $node): bool => isset($inBranch[$node['id']]));
            }
            $parentId = $options->first()['parent_id'] ?? null;

            return [
                ...$good->only(['id', 'name', 'slug', 'ava_image', 'ava_thumb', 'denominator', 'measure_id', 'unit_weight_kg', 'created_at', 'availability']),
                'description' => mb_substr(trim(strip_tags($good->description ?? '')), 0, 240),
                'url' => $this->seo->publicUrl($good),
                'public_url' => $this->seo->publicUrl($good),
                'seo' => $good->seo?->only(['slug_override', 'is_active', 'availability_status', 'min_order']),
                'country' => $good->country?->only(['id', 'name', 'flag']),
                'measurement' => $good->measurement(),
                'published_media' => $good->publishedMedia->map(fn ($media) => $media->only(['id', 'type', 'url', 'thumb_url', 'alt', 'title', 'sort_order', 'is_ava']))->all(),
                'public_purchase' => $offers->get($good->id),
                'catalog_node_id' => $sections->has($parentId) ? $parentId : null,
            ];
        });

        return [
            'goods' => $goods,
            'filters' => $filters,
            'countries' => $countries,
            'country' => $countries->firstWhere('id', $filters['country_id']),
            'fields' => $fields,
            'catalogTree' => $tree,
            'breadcrumbs' => $this->breadcrumbs($sections, $filters['node_id']),
            'site' => $this->site->site(),
        ];
    }

    private function availabilitySql(): string
    {
        return <<<'SQL'
            CASE
              WHEN EXISTS (SELECT 1 FROM good_stock_availabilities s WHERE s.good_id = goods.id)
                THEN CASE WHEN EXISTS (SELECT 1 FROM good_stock_availabilities s WHERE s.good_id = goods.id AND s.is_in_stock = 1) THEN 'in_stock' ELSE 'out_of_stock' END
              WHEN EXISTS (SELECT 1 FROM good_stock_movements m WHERE m.good_id = goods.id)
                THEN CASE WHEN EXISTS (SELECT 1 FROM good_stock_movements m WHERE m.good_id = goods.id GROUP BY m.warehouse_id, m.measure_id HAVING SUM(m.quantity_delta) > 0.000000001) THEN 'in_stock' ELSE 'out_of_stock' END
              ELSE COALESCE((SELECT NULLIF(s.availability_status, '') FROM good_seos s WHERE s.good_id = goods.id LIMIT 1), 'on_request')
            END
            SQL;
    }

    private function descendants(Collection $nodes, int $id): array
    {
        $children = $nodes->groupBy('parent_id');
        $pending = [$id];
        $seen = [];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($children->get($current, collect()) as $child) {
                $pending[] = $child['id'];
            }
        }

        return array_keys($seen);
    }

    private function tree(Collection $sections, Collection $nodes, array $matchingIds): array
    {
        $matching = array_fill_keys($matchingIds, true);
        $counts = [];
        foreach ($nodes->where('entity_type', 'good') as $good) {
            if (! isset($matching[$good['entity_id']])) {
                continue;
            }
            $parent = $good['parent_id'];
            $seen = [];
            while ($parent && $sections->has($parent) && ! isset($seen[$parent])) {
                $seen[$parent] = true;
                $counts[$parent][$good['entity_id']] = true;
                $parent = $sections->get($parent)['parent_id'];
            }
        }

        return $sections->map(fn (array $node): array => [
            'id' => $node['id'], 'parent_id' => $sections->has($node['parent_id']) ? $node['parent_id'] : null,
            'name' => $node['name'], 'public_url' => $node['public_url'],
            'goods_count' => count($counts[$node['id']] ?? []),
        ])->values()->all();
    }

    private function breadcrumbs(Collection $sections, ?int $id): array
    {
        $breadcrumbs = [];
        $seen = [];
        while ($id && $sections->has($id) && ! isset($seen[$id])) {
            $node = $sections->get($id);
            $seen[$id] = true;
            array_unshift($breadcrumbs, ['id' => $id, 'name' => $node['name'], 'public_url' => '/g?node_id='.$id]);
            $id = $node['parent_id'];
        }

        return $breadcrumbs;
    }
}
