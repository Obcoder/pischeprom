<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Search\SearchProviderException;

class PublicUrlNormalizer
{
    public function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            throw new SearchProviderException('url_policy', 'public_url_length_blocked');
        }

        $parts = parse_url($url);
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new SearchProviderException('url_policy', 'public_url_shape_blocked');
        }

        $host = mb_strtolower(rtrim((string) $parts['host'], '.'));
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                throw new SearchProviderException('url_policy', 'public_url_host_blocked');
            }
            $host = mb_strtolower($ascii);
        }
        if ($host === '' || strlen($host) > 253 || str_contains($host, '..')
            || in_array($host, ['localhost', 'metadata.google.internal'], true)
            || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new SearchProviderException('url_policy', 'public_url_host_blocked');
        }
        $ipCandidate = trim($host, '[]');
        if (filter_var($ipCandidate, FILTER_VALIDATE_IP)) {
            if (! filter_var($ipCandidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new SearchProviderException('url_policy', 'public_url_ip_blocked');
            }
            throw new SearchProviderException('url_policy', 'public_url_ip_literal_blocked');
        }
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            throw new SearchProviderException('url_policy', 'public_url_host_blocked');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($port !== null && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            throw new SearchProviderException('url_policy', 'public_url_port_blocked');
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = '/'.ltrim(preg_replace('#/{2,}#', '/', $path) ?? '/', '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $query = $this->safeQuery((string) ($parts['query'] ?? ''));

        return $scheme.'://'.$host.$path.($query !== '' ? '?'.$query : '');
    }

    public function host(string $url): string
    {
        $normalized = $this->normalize($url);

        return (string) parse_url($normalized, PHP_URL_HOST);
    }

    private function safeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }
        parse_str($query, $values);
        $allowed = ['id', 'page', 'p', 'category', 'product', 'company', 'lang', 'locale', 'search', 'q'];
        $safeValues = [];
        foreach ($values as $key => $value) {
            $normalized = mb_strtolower((string) $key);
            if (str_starts_with($normalized, 'utm_')
                || in_array($normalized, ['yclid', 'gclid', 'fbclid', '_openstat'], true)
                || ! in_array($normalized, $allowed, true)
                || ! is_scalar($value)) {
                continue;
            }
            $value = mb_substr(trim((string) $value), 0, 256);
            if (preg_match('/(?:password|passwd|api[_-]?key|token|secret|authorization|cookie|session)\s*[:=]/i', $value) === 1) {
                continue;
            }
            $safeValues[$normalized] = $value;
        }
        ksort($safeValues);

        return http_build_query($safeValues, '', '&', PHP_QUERY_RFC3986);
    }
}
