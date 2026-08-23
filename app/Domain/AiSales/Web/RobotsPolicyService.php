<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Search\SearchProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

class RobotsPolicyService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly PublicFetchPolicy $policy,
    ) {}

    public function assertAllowed(ResolvedPublicUrl $target): string
    {
        $cacheKey = 'ai-sales:stage09:robots:'.hash('sha256', $target->host);
        $rules = Cache::get($cacheKey);
        if (! is_array($rules)) {
            $rules = $this->loadRules($target);
            Cache::put($cacheKey, $rules, now()->addHour());
        }

        $path = (string) (parse_url($target->url, PHP_URL_PATH) ?: '/');
        foreach ($rules['disallow'] as $blockedPath) {
            if ($blockedPath !== '' && str_starts_with($path, $blockedPath)) {
                throw new SearchProviderException('robots', 'robots_disallow_blocked');
            }
        }

        return $rules['status'];
    }

    /** @return array{status: string, disallow: list<string>} */
    private function loadRules(ResolvedPublicUrl $target): array
    {
        $scheme = (string) parse_url($target->url, PHP_URL_SCHEME);
        $robotsUrl = $scheme.'://'.$target->host.'/robots.txt';
        $this->policy->assertDnsStable($target);
        try {
            $response = $this->http
                ->withHeaders([
                    'User-Agent' => 'pischeprom-public-research-stage09/1.0',
                    'Accept' => 'text/plain',
                    'Accept-Encoding' => 'identity',
                ])
                ->connectTimeout((int) config('ai-sales.prospecting.limits.public_fetch_connect_timeout_seconds', 3))
                ->timeout((int) config('ai-sales.prospecting.limits.public_fetch_timeout_seconds', 10))
                ->withoutRedirecting()
                ->withOptions($this->policy->pinnedTransportOptions($target))
                ->get($robotsUrl);
        } catch (ConnectionException) {
            throw new SearchProviderException('robots', 'robots_unavailable');
        }
        $this->policy->assertDnsStable($target);
        if ($response->status() === 404) {
            return ['status' => 'not_present', 'disallow' => []];
        }
        if (! $response->successful() || $response->redirect() || strlen($response->body()) > 65_536) {
            throw new SearchProviderException('robots', 'robots_unavailable');
        }
        $contentType = mb_strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'text/plain')) {
            throw new SearchProviderException('robots', 'robots_content_type_blocked');
        }
        $contentEncoding = mb_strtolower(trim((string) $response->header('Content-Encoding')));
        if ($contentEncoding !== '' && $contentEncoding !== 'identity') {
            throw new SearchProviderException('robots', 'robots_compression_blocked');
        }

        $disallow = [];
        $applies = false;
        foreach (preg_split('/\R/u', $response->body()) ?: [] as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = mb_strtolower($name);
            if ($name === 'user-agent') {
                $applies = $value === '*' || mb_strtolower($value) === 'pischeprom-public-research-stage09';
            } elseif ($name === 'disallow' && $applies && $value !== '') {
                $disallow[] = mb_substr($value, 0, 512);
            }
        }

        return ['status' => 'allowed', 'disallow' => array_values(array_unique(array_slice($disallow, 0, 100)))];
    }
}
