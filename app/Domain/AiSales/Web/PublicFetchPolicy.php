<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Search\SearchProviderException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class PublicFetchPolicy
{
    public function __construct(
        private readonly PublicUrlNormalizer $urls,
        private readonly RegistrableDomainResolver $domains,
        private readonly PublicDnsResolver $dns,
        private readonly CacheRepository $cache,
    ) {}

    public function authorize(string $url, ?string $requiredRegistrableDomain = null): ResolvedPublicUrl
    {
        $url = $this->urls->normalize($url);
        $host = $this->urls->host($url);
        $domain = $this->domains->resolve($host);
        if ($requiredRegistrableDomain !== null && ! hash_equals($requiredRegistrableDomain, $domain)) {
            throw new SearchProviderException('fetch_policy', 'cross_domain_redirect_blocked');
        }
        $ips = $this->dns->resolve($host);
        if ($ips === []) {
            throw new SearchProviderException('fetch_policy', 'public_dns_resolution_failed');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)
                || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new SearchProviderException('fetch_policy', 'private_or_reserved_dns_blocked');
            }
        }
        sort($ips, SORT_STRING);

        return new ResolvedPublicUrl($url, $host, $domain, array_values(array_unique($ips)));
    }

    public function assertDnsStable(ResolvedPublicUrl $resolved): void
    {
        $current = $this->dns->resolve($resolved->host);
        sort($current, SORT_STRING);
        if ($current === [] || $current !== $resolved->ipAddresses) {
            throw new SearchProviderException('fetch_policy', 'dns_rebinding_blocked');
        }
        foreach ($current as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new SearchProviderException('fetch_policy', 'dns_rebinding_blocked');
            }
        }
    }

    /** @return array<string, mixed> */
    public function pinnedTransportOptions(ResolvedPublicUrl $resolved): array
    {
        if (! defined('CURLOPT_RESOLVE') || $resolved->ipAddresses === []) {
            throw new SearchProviderException('fetch_policy', 'public_fetch_dns_pinning_unavailable');
        }

        $scheme = (string) parse_url($resolved->url, PHP_URL_SCHEME);
        $port = $scheme === 'https' ? 443 : 80;
        $ip = $resolved->ipAddresses[0];
        $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        return [
            'verify' => true,
            'allow_redirects' => false,
            'decode_content' => false,
            'curl' => [CURLOPT_RESOLVE => ["{$resolved->host}:{$port}:{$address}"]],
        ];
    }

    public function reserveDomainPage(string $registrableDomain): void
    {
        $key = 'ai-sales:stage09:domain-pages:'.hash('sha256', $registrableDomain);
        $count = (int) $this->cache->increment($key);
        $this->cache->put($key, $count, now()->addHour());
        if ($count > (int) config('ai-sales.prospecting.limits.max_public_fetches_per_domain', 5)) {
            throw new SearchProviderException('fetch_policy', 'per_domain_page_budget_blocked');
        }
    }
}
