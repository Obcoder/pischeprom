<?php

namespace App\Services\Logistics\Map;

final class GisReleaseMetadataService
{
    public function osmDataVersion(): ?string
    {
        $release = $this->release();
        $version = ($release['status'] ?? null) === 'active'
            ? ($release['osm_data_version'] ?? null)
            : null;

        return is_string($version) && $version !== ''
            ? $version
            : (config('logistics.osm_data_version') ?: null);
    }

    /** @return array<string, mixed> */
    public function release(): array
    {
        $manifest = $this->readJson(config('logistics.map.release_manifest_path'));

        if ($manifest === null) {
            return [
                'status' => 'unavailable',
                'coverage' => (string) config('logistics.map.coverage', 'Russia'),
                'osm_data_version' => config('logistics.osm_data_version'),
                'release' => null,
                'updated_at' => null,
                'pbf' => null,
                'valhalla' => null,
                'pmtiles' => null,
                'smoke_tests' => null,
            ];
        }

        $activation = $this->readJson(config('logistics.map.activation_status_path'));
        $releaseName = $this->string($manifest, 'release');
        $isActive = $releaseName !== null
            && $this->string($activation ?? [], 'status') === 'active'
            && $this->string($activation ?? [], 'production_smoke') === 'passed'
            && hash_equals($releaseName, (string) $this->string($activation ?? [], 'release'));

        return [
            'status' => $isActive ? 'active' : ($this->string($manifest, 'status') ?: 'unavailable'),
            'coverage' => $this->string($manifest, 'coverage')
                ?: (string) config('logistics.map.coverage', 'Russia'),
            'osm_data_version' => $this->string($manifest, 'osm_data_version'),
            'release' => $releaseName,
            'updated_at' => ($isActive ? $this->string($activation ?? [], 'activated_at') : null)
                ?: $this->string($manifest, 'activated_at')
                ?: $this->string($manifest, 'verified_at'),
            'pbf' => $this->component($manifest, 'pbf', [
                'source_url', 'resolved_source_url', 'data_date', 'osm_data_timestamp', 'size_bytes', 'md5',
            ]),
            'valhalla' => $this->component($manifest, 'valhalla', [
                'version', 'graph_size_bytes', 'build_duration_seconds', 'peak_rss_kb',
            ]),
            'pmtiles' => $this->component($manifest, 'pmtiles', [
                'spec_version', 'planetiler_version', 'java_version', 'size_bytes',
                'sha256', 'build_duration_seconds', 'peak_rss_kb',
            ]),
            'smoke_tests' => $this->component($manifest, 'smoke_tests', [
                'status', 'checked_at', 'results',
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        $release = $this->release();
        $preflight = $this->readJson(config('logistics.map.preflight_status_path'));
        $range = $this->readJson(config('logistics.map.range_status_path'));
        $activation = $this->readJson(config('logistics.map.activation_status_path'));
        $productionSmoke = $this->readJson(config('logistics.map.production_smoke_status_path'));

        return [
            ...$release,
            'preflight' => $preflight === null ? null : [
                'result' => $this->string($preflight, 'result'),
                'mode' => $this->string($preflight, 'mode'),
                'checked_at' => $this->string($preflight, 'checked_at'),
                'cpu' => $this->selected($preflight['cpu'] ?? null, [
                    'model', 'physical_cores', 'logical_cores', 'load_average', 'load_per_core',
                ]),
                'memory' => $this->selected($preflight['memory'] ?? null, [
                    'total_bytes', 'available_bytes', 'used_bytes', 'swap_total_bytes',
                    'swap_available_bytes',
                ]),
                'disk' => $this->selected($preflight['disk'] ?? null, [
                    'free_bytes', 'free_inodes', 'filesystem', 'storage_kind',
                ]),
                'requirements' => $this->selected($preflight['requirements'] ?? null, [
                    'disk_required_bytes', 'ram_required_bytes', 'app_disk_reserve_bytes',
                    'app_ram_reserve_bytes',
                ]),
                'warnings' => $this->stringList($preflight['warnings'] ?? null),
                'failures' => $this->stringList($preflight['failures'] ?? null),
            ],
            'range_requests' => $range === null ? null : [
                'healthy' => (bool) ($range['healthy'] ?? false),
                'head_status_code' => isset($range['head_status_code']) ? (int) $range['head_status_code'] : null,
                'content_length' => isset($range['content_length']) ? (int) $range['content_length'] : null,
                'content_type' => $this->string($range, 'content_type'),
                'status_code' => isset($range['status_code']) ? (int) $range['status_code'] : null,
                'accept_ranges' => $this->string($range, 'accept_ranges'),
                'content_range' => $this->string($range, 'content_range'),
                'checked_at' => $this->string($range, 'checked_at'),
            ],
            'activation' => $activation === null ? null : [
                'status' => $this->string($activation, 'status'),
                'release' => $this->string($activation, 'release'),
                'previous_release' => $this->string($activation, 'previous_release'),
                'activated_at' => $this->string($activation, 'activated_at'),
                'production_smoke' => $this->string($activation, 'production_smoke'),
            ],
            'production_smoke_tests' => $productionSmoke === null ? null : $this->selected($productionSmoke, [
                'status', 'kind', 'coverage', 'release', 'checked_at', 'results',
            ]),
        ];
    }

    private function readJson(mixed $path): ?array
    {
        if (! is_string($path) || $path === '' || ! is_file($path) || is_link($path)) {
            return null;
        }

        $size = @filesize($path);
        if (! is_int($size) || $size < 2 || $size > 1_048_576) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param list<string> $keys */
    private function component(array $manifest, string $key, array $keys): ?array
    {
        return $this->selected($manifest[$key] ?? null, $keys);
    }

    /** @param list<string> $keys */
    private function selected(mixed $value, array $keys): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $selected = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $value)) {
                $selected[$key] = $value[$key];
            }
        }

        return $selected;
    }

    private function string(array $value, string $key): ?string
    {
        return isset($value[$key]) && is_scalar($value[$key])
            ? (string) $value[$key]
            : null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, 'is_string'))
            : [];
    }
}
