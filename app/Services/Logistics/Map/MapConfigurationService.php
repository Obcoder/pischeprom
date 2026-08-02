<?php

namespace App\Services\Logistics\Map;

use RuntimeException;

final class MapConfigurationService
{
    public function __construct(private readonly GisReleaseMetadataService $metadata) {}

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $release = $this->metadata->release();
        $styleVersion = (string) config('logistics.map.style_version', '1');
        $assetVersion = $this->assetVersion($release, $styleVersion);
        // A release may be checksum/smoke verified while it is still only a
        // staging candidate. Never expose that archive as the production
        // basemap until the activation manifest confirms the same release.
        $releaseReady = ($release['status'] ?? null) === 'active';
        $mapReady = $releaseReady && (int) data_get($release, 'pmtiles.size_bytes', 0) > 0;

        return [
            'enabled' => (bool) config('logistics.map.enabled') && $mapReady,
            'coverage' => (string) config('logistics.map.coverage', 'Russia'),
            'style_url' => $this->versionedUrl((string) config('logistics.map.style_url'), $assetVersion),
            'style_version' => $styleVersion,
            'pmtiles_url' => $this->versionedUrl((string) config('logistics.map.pmtiles_url'), $assetVersion),
            'attribution' => (string) config('logistics.map.attribution'),
            'default_center' => config('logistics.map.default_center', [94.0, 66.0]),
            'default_zoom' => (float) config('logistics.map.default_zoom', 2.3),
            'entity_layer_available' => \Illuminate\Support\Facades\Schema::hasTable('entity_locations'),
            'release' => $release,
        ];
    }

    /** @return array<string, mixed> */
    public function style(): array
    {
        $path = resource_path('maps/logistics-russia-style.json');
        $contents = @file_get_contents($path);
        $style = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($style)) {
            throw new RuntimeException('Logistics map style is unavailable.');
        }

        $release = $this->metadata->release();
        $assetVersion = $this->assetVersion(
            $release,
            (string) config('logistics.map.style_version', '1'),
        );
        $pmtilesUrl = $this->versionedUrl((string) config('logistics.map.pmtiles_url'), $assetVersion);
        $style['sources']['logistics-basemap']['url'] = 'pmtiles://'.$pmtilesUrl;
        $style['sources']['logistics-basemap']['attribution'] = (string) config('logistics.map.attribution');
        $style['glyphs'] = $this->versionedUrl((string) config('logistics.map.glyphs_url'), $assetVersion);

        $sprite = trim((string) config('logistics.map.sprite_url'));
        if ($sprite !== '') {
            $style['sprite'] = $this->versionedUrl($sprite, $assetVersion);
        } else {
            unset($style['sprite']);
        }

        return $style;
    }

    private function sameOriginUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            throw new RuntimeException('Logistics map URLs must be same-origin absolute paths.');
        }

        return $url;
    }

    /** @param array<string, mixed> $release */
    private function assetVersion(array $release, string $styleVersion): string
    {
        $releaseName = is_string($release['release'] ?? null) ? $release['release'] : null;

        return $releaseName ? $styleVersion.'-'.$releaseName : $styleVersion;
    }

    private function versionedUrl(string $url, string $version): string
    {
        $url = $this->sameOriginUrl($url);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query(['v' => $version]);
    }
}
