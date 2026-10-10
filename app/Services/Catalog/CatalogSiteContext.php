<?php

namespace App\Services\Catalog;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\CatalogSiteDomain;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Services\Seo\GoodSeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** The current host's public catalog. Staff APIs deliberately do not use this scope. */
class CatalogSiteContext
{
    private array $snapshots = [];

    private function snapshot(string $key, callable $load): mixed
    {
        // HTTP requests read one consistent catalog. Console jobs and tests can
        // mutate catalog data between calls and must always read current state.
        if (app()->runningInConsole()) {
            return $load();
        }
        $requestKey = spl_object_id(request()).':'.request()->getHost().':'.$key;
        if (! array_key_exists($requestKey, $this->snapshots)) {
            $this->snapshots[$requestKey] = $load();
        }

        return $this->snapshots[$requestKey];
    }

    public function site(): ?array
    {
        return $this->snapshot('site', fn (): ?array => $this->resolveSite());
    }

    private function resolveSite(): ?array
    {
        $host = CatalogHost::normalize(request()->getHost());
        if (! $host || ! Schema::hasTable('catalog_site_domains')) {
            return null;
        }
        $binding = CatalogSiteDomain::query()->with('node.level')->where('hostname', $host)->first();
        $node = $binding?->node;
        if (! $node || ! $node->level?->is_domain || $node->parent_id !== null) {
            return null;
        }

        return ['id' => $node->id, 'name' => $node->name, 'hostname' => $host];
    }

    public function domainNodeId(): ?int
    {
        return $this->site()['id'] ?? null;
    }

    public function isScoped(): bool
    {
        return $this->snapshot('scoped', fn (): bool => $this->hasSites());
    }

    private function hasSites(): bool
    {
        if (! Schema::hasTable('catalog_nodes')) {
            return false;
        }

        return CatalogNode::query()->whereHas('level', fn (Builder $query) => $query->where('is_domain', true))->exists()
            || (Schema::hasTable('catalog_site_domains') && CatalogSiteDomain::query()->exists());
    }

    public function scopeGoods(Builder|Relation $query): Builder|Relation
    {
        return $this->scopeEntities($query, 'good');
    }

    public function scopeEntities(Builder|Relation $query, string $entityType): Builder|Relation
    {
        if (! $this->isScoped()) {
            return $query;
        }

        return $query->whereIn($query->getModel()->qualifyColumn('id'), $this->entityIds($entityType));
    }

    public function allowsGood(int $id): bool
    {
        return $this->allowsEntity('good', $id);
    }

    public function allowsEntity(string $entityType, int $id): bool
    {
        return ! $this->isScoped() || in_array($id, $this->entityIds($entityType), true);
    }

    public function goodIds(): ?array
    {
        return $this->isScoped() ? $this->entityIds('good') : null;
    }

    public function nodeIds(): ?array
    {
        return $this->isScoped() ? $this->visibleNodes()->keys()->all() : null;
    }

    private function entityIds(string $entityType): array
    {
        return $this->visibleNodes()->where('entity_type', $entityType)->pluck('entity_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /** Lightweight navigation snapshot; full prices, stock and SEO load only for the selected goods page. */
    public function visibleNodes(): Collection
    {
        return $this->snapshot('nodes', fn (): Collection => $this->loadVisibleNodes());
    }

    private function loadVisibleNodes(): Collection
    {
        if (! Schema::hasTable('catalog_nodes')) {
            return collect();
        }
        $nodes = CatalogNode::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get(['id', 'level_id', 'parent_id', 'entity_type', 'entity_id', 'name', 'slug', 'image', 'is_published', 'is_featured', 'sort_order'])
            ->map(fn (CatalogNode $node): array => $node->toArray());
        $sources = [
            'category' => Category::query()->whereIn('id', $nodes->where('entity_type', 'category')->pluck('entity_id'))->get(['id', 'name', 'slug', 'image', 'is_published', 'is_featured'])->keyBy('id'),
            'product' => Product::without(['category', 'manufacturers'])->whereIn('id', $nodes->where('entity_type', 'product')->pluck('entity_id'))->get(['id', 'rus', 'is_published'])->keyBy('id'),
            'good' => Good::without('measure')->with('seo:id,good_id,slug_override,is_active')->whereIn('id', $nodes->where('entity_type', 'good')->pluck('entity_id'))->get(['id', 'name', 'slug', 'ava_image', 'ava_thumb', 'is_published'])->keyBy('id'),
        ];
        $nodes = $nodes->map(function (array $node) use ($sources): array {
            if ($node['entity_type']) {
                $source = $sources[$node['entity_type']][$node['entity_id']] ?? null;
                $node['is_published'] = (bool) $source?->is_published;
                if ($source) {
                    $node['name'] = $source instanceof Product ? $source->rus : $source->name;
                    $node['slug'] = $source->slug ?? $node['slug'];
                    if ($source instanceof Good) {
                        $node['offer_url'] = app(GoodSeoService::class)->publicUrl($source);
                    }
                    if ($source instanceof Category) {
                        $node['is_featured'] = (bool) $source->is_featured;
                    }
                    if ($source instanceof Good || $source instanceof Category) {
                        $node['image'] = $source instanceof Good ? ($source->ava_image ?: $source->ava_thumb) : $source->image;
                    }
                }
            }

            return $node;
        });
        $domainLevels = CatalogLevel::query()->where('is_domain', true)->pluck('id')->all();
        $paths = app(CatalogPaths::class)->forNodes($nodes, $domainLevels);
        $nodes = $nodes->map(function (array $node) use ($paths): array {
            $node['catalog_path'] = $paths[$node['id']] ?? null;
            $node['public_url'] = $node['entity_type'] === 'good'
                ? ($node['offer_url'] ?? route('public.goods.show', (string) ($node['entity_id'] ?: 0)))
                : ($node['catalog_path'] !== null ? route('public.catalog.path', ['path' => $node['catalog_path']]) : null);

            return $node;
        });

        return $this->filterNodes($nodes);
    }

    /** Hide unpublished ancestors, broken trees, other sites and the structural Domain itself. */
    public function filterNodes(Collection $nodes): Collection
    {
        $nodes = $nodes->keyBy('id');
        $domainLevels = array_fill_keys(CatalogLevel::query()->where('is_domain', true)->pluck('id')->all(), true);
        $scoped = $nodes->contains(fn (array $node): bool => isset($domainLevels[$node['level_id']]))
            || (Schema::hasTable('catalog_site_domains') && CatalogSiteDomain::query()->exists());
        $domainId = $scoped ? $this->domainNodeId() : null;
        if ($scoped && $domainId === null) {
            return collect();
        }
        $visibility = [];
        foreach ($nodes as $node) {
            $chain = [];
            $current = $node;
            $visible = ! $scoped;
            while ($current) {
                $id = $current['id'];
                if (array_key_exists($id, $visibility)) {
                    $visible = $visibility[$id];
                    break;
                }
                if (isset($chain[$id]) || ! $current['is_published']) {
                    $visible = false;
                    break;
                }
                $chain[$id] = true;
                if (isset($domainLevels[$current['level_id']])) {
                    $visible = $id === $domainId && $current['parent_id'] === null;
                    break;
                }
                if (! $current['parent_id']) {
                    break;
                }
                $current = $nodes->get($current['parent_id']);
                if (! $current) {
                    $visible = false;
                }
            }
            foreach (array_keys($chain) as $id) {
                $visibility[$id] = $visible;
            }
        }

        return $nodes->filter(fn (array $node): bool => ($visibility[$node['id']] ?? false) && ! isset($domainLevels[$node['level_id']]))
            ->map(function (array $node) use ($domainId): array {
                if ($domainId !== null && $node['parent_id'] === $domainId) {
                    $node['parent_id'] = null;
                }

                return $node;
            })->sortBy([['sort_order', 'asc'], ['name', 'asc'], ['id', 'asc']]);
    }
}
