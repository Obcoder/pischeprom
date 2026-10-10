<?php

namespace App\Services\Catalog;

use App\Models\CatalogLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PublicCatalogService
{
    public function __construct(private CatalogService $catalog) {}

    public function showcase(int $limit = 12): array
    {
        return $this->visibleNodes()
            ->filter(fn (array $node): bool => (bool) $node['is_featured'])
            ->take($limit)
            ->map(fn (array $node): array => $this->card($node))
            ->values()
            ->all();
    }

    public function page(int $id): ?array
    {
        $nodes = $this->visibleNodes();
        $node = $nodes->get($id);

        return $node ? $this->pagePayload($node, $nodes) : null;
    }

    /** Resolve several published pages using the same visibility snapshot. */
    public function pages(array $ids): Collection
    {
        $nodes = $this->visibleNodes();

        return $nodes->only($ids)->map(fn (array $node): array => $this->pagePayload($node, $nodes));
    }

    /** Staff-only callers may preview a draft even while its branch is hidden. */
    public function previewPage(int $id): ?array
    {
        $nodes = $this->catalog->nodes()->keyBy('id');
        $node = $nodes->get($id);

        return $node && $node['catalog_path'] !== null ? $this->pagePayload($node, $nodes) : null;
    }

    public function goodsInBranch(int $id, bool $preview = false): array
    {
        $nodes = $preview ? $this->catalog->nodes()->keyBy('id') : $this->visibleNodes();
        $children = $nodes->groupBy('parent_id');
        $pending = [$id];
        $seen = [];
        $goods = [];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($seen[$current]) || ! $nodes->has($current)) {
                continue;
            }
            $seen[$current] = true;
            $node = $nodes->get($current);
            if ($current !== $id && ! $node['is_published']) {
                continue;
            }
            if ($node['entity_type'] === 'good') {
                $goods[] = (int) $node['entity_id'];
            }
            foreach ($children->get($current, collect()) as $child) {
                $pending[] = $child['id'];
            }
        }

        return array_values(array_unique($goods));
    }

    public function pageByPath(string $path): ?array
    {
        $nodes = $this->visibleNodes();
        $matches = $nodes->whereStrict('catalog_path', $path);

        return $matches->count() === 1 ? $this->pagePayload($matches->first(), $nodes) : null;
    }

    /** Public category navigation follows the catalog's actual placement. */
    public function categoryUrls(): array
    {
        return $this->visibleNodes()->where('entity_type', 'category')->sortBy('id')
            ->unique('entity_id')->pluck('public_url', 'entity_id')->all();
    }

    private function pagePayload(array $node, Collection $nodes): array
    {
        $id = $node['id'];
        $level = CatalogLevel::query()->with(['fields' => fn ($query) => $query->where('is_public', true)->orderBy('sort_order')->orderBy('id')])->find($node['level_id']);
        $breadcrumbs = [];
        $parent = $node['parent_id'] ? $nodes->get($node['parent_id']) : null;
        $seen = [$id => true];

        while ($parent && ! isset($seen[$parent['id']])) {
            $seen[$parent['id']] = true;
            array_unshift($breadcrumbs, $this->card($parent));
            $parent = $parent['parent_id'] ? $nodes->get($parent['parent_id']) : null;
        }

        $values = (array) ($node['properties'] ?? []);
        $properties = $level?->fields
            ->filter(fn ($field): bool => array_key_exists($field->key, $values) && $values[$field->key] !== null && $values[$field->key] !== '')
            ->map(fn ($field): array => [
                'label' => $field->label,
                'type' => $field->type,
                'value' => $values[$field->key],
            ])
            ->values()
            ->all() ?? [];

        return [
            'node' => [
                ...$this->card($node),
                'slug' => $node['slug'],
                'level_name' => $level?->name,
                'description' => $node['description'],
                'h1' => $node['h1'] ?? null,
                'offer_url' => $node['offer_url'] ?? null,
            ],
            'breadcrumbs' => $breadcrumbs,
            'children' => $nodes
                ->filter(fn (array $child): bool => (int) $child['parent_id'] === $id)
                ->map(fn (array $child): array => $this->card($child))
                ->values()->all(),
            'properties' => $properties,
            'seo' => [
                'h1' => trim($node['h1'] ?? '') ?: $node['name'],
                'title' => $node['meta_title'] ?: $node['name'].' — ПИЩЕПРОМ-СЕРВЕР',
                'description' => $node['meta_description'] ?: mb_substr(strip_tags($node['description'] ?: $node['name']), 0, 240),
                'canonical' => $node['public_url'],
                'image' => $this->image($node['image']),
                ...($node['public_seo'] ?? []),
            ],
        ];
    }

    private function visibleNodes(): Collection
    {
        if (! Schema::hasTable('catalog_nodes')) {
            return collect();
        }

        $nodes = $this->catalog->nodes()->keyBy('id');
        $visibility = [];

        // A published child remains private while any ancestor is hidden or
        // missing. Resolve iteratively so arbitrary depth cannot recurse forever.
        foreach ($nodes as $node) {
            $path = [];
            $current = $node;
            $visible = true;

            while ($current) {
                $id = $current['id'];
                if (array_key_exists($id, $visibility)) {
                    $visible = $visibility[$id];
                    break;
                }
                if (isset($path[$id]) || ! $current['is_published']) {
                    $visible = false;
                    break;
                }
                $path[$id] = true;
                if (! $current['parent_id']) {
                    break;
                }
                $current = $nodes->get($current['parent_id']);
                if (! $current) {
                    $visible = false;
                }
            }

            foreach (array_keys($path) as $id) {
                $visibility[$id] = $visible;
            }
        }

        return $nodes
            ->filter(fn (array $node): bool => $visibility[$node['id']] ?? false)
            ->sortBy([['sort_order', 'asc'], ['name', 'asc'], ['id', 'asc']]);
    }

    private function card(array $node): array
    {
        return [
            'id' => $node['id'],
            'name' => $node['name'],
            'image' => $this->image($node['image']),
            'public_url' => $node['public_url'],
        ];
    }

    private function image(?string $value): ?string
    {
        if (! $value || ! preg_match('~^(https?://|/(?!/))~i', $value)) {
            return null;
        }

        return $value;
    }
}
