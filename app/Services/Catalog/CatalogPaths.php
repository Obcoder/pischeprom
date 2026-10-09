<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogPaths
{
    /**
     * Build paths from the complete tree, including hidden nodes, so toggling
     * publication never changes the URL of a neighbouring published section.
     * Domain levels organize the editor; their descendants start at the first
     * ordinary section, while the domain itself still has its own public page.
     *
     * @return array<int, string>
     */
    public function forNodes(Collection $nodes, array $domainLevelIds): array
    {
        $nodes = $nodes->keyBy('id');
        $domains = array_fill_keys($domainLevelIds, true);
        $chains = [];
        $segments = [];
        $suffixed = [];

        foreach ($nodes as $node) {
            $segments[$node['id']] = Str::slug($node['slug'] ?: $node['name']) ?: 'catalog';
            $chain = [];
            $seen = [];
            $current = $node;

            while ($current) {
                $id = $current['id'];
                if (isset($seen[$id])) {
                    continue 2;
                }
                $seen[$id] = true;
                if ($id === $node['id'] || ! isset($domains[$current['level_id']])) {
                    array_unshift($chain, $id);
                }
                if (! $current['parent_id']) {
                    break;
                }
                $current = $nodes->get($current['parent_id']);
                if (! $current) {
                    continue 2;
                }
            }

            $chains[$node['id']] = $chain;
        }

        // A numeric first segment is reserved for permanent legacy ID routes.
        foreach ($chains as $chain) {
            if (ctype_digit($segments[$chain[0]])) {
                $suffixed[$chain[0]] = true;
            }
        }

        do {
            $paths = [];
            $groups = [];
            foreach ($chains as $id => $chain) {
                $path = implode('/', array_map(fn (int $part): string => $segments[$part].(isset($suffixed[$part]) ? '-node-'.$part : ''), $chain));
                $paths[$id] = $path;
                $groups[$path][] = $id;
            }

            $collisions = array_filter($groups, fn (array $ids): bool => count($ids) > 1);
            if ($collisions === []) {
                return $paths;
            }

            // Resolve parents first; their children then inherit the corrected
            // path without unnecessary suffixes on every descendant.
            $depth = min(array_map(fn (array $ids): int => count($chains[$ids[0]]), $collisions));
            $changed = false;
            foreach ($collisions as $ids) {
                if (count($chains[$ids[0]]) !== $depth) {
                    continue;
                }
                foreach ($ids as $id) {
                    if (! isset($suffixed[$id])) {
                        $suffixed[$id] = true;
                        $changed = true;
                    }
                }
            }
        } while ($changed);

        // Never select an arbitrary node if a malformed tree is ambiguous.
        return array_diff_key($paths, array_flip(array_merge(...array_values($collisions))));
    }
}
