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
        $releaseName = is_string($release['release'] ?? null) ? $release['release'] : null;
        $pmtilesUrl = $this->assetUrl((string) config('logistics.map.pmtiles_url'));
        $deliveryReady = ! $this->isExternalUrl($pmtilesUrl)
            || $this->publicationMatches($release, $pmtilesUrl, $releaseName);

        return [
            'enabled' => (bool) config('logistics.map.enabled') && $mapReady && $deliveryReady,
            'delivery' => $this->isExternalUrl($pmtilesUrl) ? 'object_storage_cdn' : 'same_origin',
            'coverage' => (string) config('logistics.map.coverage', 'Russia'),
            'style_url' => $this->versionedUrl(
                $this->sameOriginUrl((string) config('logistics.map.style_url')),
                $assetVersion,
                $releaseName,
            ),
            'style_version' => $styleVersion,
            'pmtiles_url' => $this->versionedUrl(
                $pmtilesUrl,
                $assetVersion,
                $releaseName,
            ),
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
        $releaseName = is_string($release['release'] ?? null) ? $release['release'] : null;
        $pmtilesUrl = $this->versionedAssetUrl(
            (string) config('logistics.map.pmtiles_url'),
            $assetVersion,
            $releaseName,
        );
        $style['sources']['logistics-basemap']['url'] = 'pmtiles://'.$pmtilesUrl;
        $style['sources']['logistics-basemap']['attribution'] = (string) config('logistics.map.attribution');
        $style['glyphs'] = $this->versionedAssetUrl(
            (string) config('logistics.map.glyphs_url'),
            $assetVersion,
            $releaseName,
        );

        $sprite = trim((string) config('logistics.map.sprite_url'));
        if ($sprite !== '') {
            $style['sprite'] = $this->versionedAssetUrl($sprite, $assetVersion, $releaseName);
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

    private function assetUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $this->sameOriginUrl($url);
        }

        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new RuntimeException('Logistics map asset URL is invalid.');
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('External logistics map assets require a trusted HTTPS origin.');
        }

        $origin = 'https://'.strtolower($parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':'.(int) $parts['port'];
        }

        $allowedOrigins = array_map(
            fn (mixed $allowed): ?string => $this->normalizedOrigin($allowed),
            (array) config('logistics.map.asset_origins', []),
        );

        if (! in_array($origin, array_filter($allowedOrigins), true)) {
            throw new RuntimeException('External logistics map asset origin is not allowlisted.');
        }

        return $url;
    }

    /** @param array<string, mixed> $release */
    private function assetVersion(array $release, string $styleVersion): string
    {
        $releaseName = is_string($release['release'] ?? null) ? $release['release'] : null;

        return $releaseName ? $styleVersion.'-'.$releaseName : $styleVersion;
    }

    private function normalizedOrigin(mixed $origin): ?string
    {
        if (! is_string($origin) || $origin === '' || preg_match('/[\x00-\x20\x7f]/', $origin)) {
            return null;
        }

        $parts = parse_url($origin);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        $normalized = 'https://'.strtolower($parts['host']);
        if (isset($parts['port'])) {
            $normalized .= ':'.(int) $parts['port'];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $release */
    private function publicationMatches(array $release, string $pmtilesUrl, ?string $releaseName): bool
    {
        if ($releaseName === null) {
            return false;
        }

        $publication = $this->metadata->mapPublication();
        $publishedPmtiles = is_array($publication['pmtiles'] ?? null)
            ? $publication['pmtiles']
            : [];
        $publicBase = is_string($publication['public_base_url'] ?? null)
            ? rtrim($publication['public_base_url'], '/')
            : null;
        $expectedUrl = str_replace('{release}', rawurlencode($releaseName), $pmtilesUrl);
        $expectedSha = data_get($release, 'pmtiles.sha256');
        $expectedSize = (int) data_get($release, 'pmtiles.size_bytes', 0);
        $releaseAssetBase = $publicBase === null
            ? null
            : $publicBase.'/releases/'.rawurlencode($releaseName);

        try {
            $glyphsUrl = str_replace(
                '{release}',
                rawurlencode($releaseName),
                $this->assetUrl((string) config('logistics.map.glyphs_url')),
            );
            $sprite = trim((string) config('logistics.map.sprite_url'));
            $spriteUrl = $sprite === ''
                ? null
                : str_replace('{release}', rawurlencode($releaseName), $this->assetUrl($sprite));
        } catch (RuntimeException) {
            return false;
        }

        return ($publication['status'] ?? null) === 'verified'
            && ($publication['release'] ?? null) === $releaseName
            && is_string($releaseAssetBase)
            && $expectedUrl === $releaseAssetBase.'/russia.pmtiles'
            && str_starts_with($glyphsUrl, $releaseAssetBase.'/assets/fonts/')
            && ($spriteUrl === null || str_starts_with($spriteUrl, $releaseAssetBase.'/assets/sprites/'))
            && ($publishedPmtiles['url'] ?? null) === $expectedUrl
            && is_string($expectedSha)
            && preg_match('/^[0-9a-f]{64}$/', $expectedSha) === 1
            && hash_equals($expectedSha, (string) ($publishedPmtiles['sha256'] ?? ''))
            && $expectedSize > 0
            && (int) ($publishedPmtiles['size_bytes'] ?? 0) === $expectedSize
            && ($publishedPmtiles['range_requests'] ?? null) === 'passed'
            && ($publishedPmtiles['cors'] ?? null) === 'passed';
    }

    private function isExternalUrl(string $url): bool
    {
        return str_starts_with($url, 'https://');
    }

    private function versionedAssetUrl(string $url, string $version, ?string $release): string
    {
        return $this->versionedUrl($this->assetUrl($url), $version, $release);
    }

    private function versionedUrl(string $url, string $version, ?string $release): string
    {
        $url = str_replace('{release}', rawurlencode($release ?: 'unavailable'), $url);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query(['v' => $version]);
    }
}
