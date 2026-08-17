<?php

namespace App\Domain\AiSales\FindBuyers\Canary;

use App\Domain\AiSales\Search\SearchProviderException;
use App\Services\YandexSearchService;
use Psr\Http\Message\RequestInterface;

final class FindBuyersCanaryHttpGuard
{
    public const MAX_TOTAL_REQUESTS = 8;

    public const MAX_YANDEX_REQUESTS = 1;

    public const MAX_PUBLIC_REQUESTS = 7;

    /** @var array<string, true> */
    private array $allowedPublicHosts = [];

    private int $totalRequests = 0;

    private int $yandexRequests = 0;

    private int $publicRequests = 0;

    /** @param list<string> $hosts */
    public function allowPublicHosts(array $hosts): void
    {
        $normalized = [];
        foreach (array_slice(array_values(array_unique($hosts)), 0, FindBuyersCanaryJobGuard::MAX_FETCH_DOMAINS) as $host) {
            $host = mb_strtolower(rtrim(trim($host), '.'));
            if ($host === '' || preg_match('/^[a-z0-9.-]{1,253}$/', $host) !== 1) {
                throw new SearchProviderException('canary_policy', 'stage11b_public_host_invalid');
            }
            $normalized[$host] = true;
        }
        $this->allowedPublicHosts = $normalized;
    }

    public function authorize(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $scheme = mb_strtolower($uri->getScheme());
        $host = mb_strtolower(rtrim($uri->getHost(), '.'));
        $port = $uri->getPort();
        $method = mb_strtoupper($request->getMethod());

        if (! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || ($port !== null && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)))) {
            throw new SearchProviderException('canary_policy', 'stage11b_http_target_blocked');
        }
        if ($this->totalRequests >= self::MAX_TOTAL_REQUESTS) {
            throw new SearchProviderException('canary_budget', 'stage11b_total_http_budget_exceeded');
        }

        $configuredYandexHost = mb_strtolower((string) config('services.yandex_search.host'));
        if ($configuredYandexHost !== '' && hash_equals($configuredYandexHost, $host)) {
            if ($scheme !== 'https'
                || $method !== 'POST'
                || $uri->getPath() !== YandexSearchService::ENDPOINT_PATH
                || $this->yandexRequests >= self::MAX_YANDEX_REQUESTS) {
                throw new SearchProviderException('canary_policy', 'stage11b_yandex_request_blocked');
            }
            $this->yandexRequests++;
            $this->totalRequests++;

            return $request;
        }

        if ($host === 'api.timeweb.ai') {
            throw new SearchProviderException('canary_policy', 'stage11b_timeweb_request_blocked');
        }
        if (! isset($this->allowedPublicHosts[$host])
            || $method !== 'GET'
            || $this->publicRequests >= self::MAX_PUBLIC_REQUESTS) {
            throw new SearchProviderException('canary_policy', 'stage11b_public_request_blocked');
        }
        $this->publicRequests++;
        $this->totalRequests++;

        return $request;
    }

    public function canAttemptPage(): bool
    {
        return self::MAX_TOTAL_REQUESTS - $this->totalRequests >= 3
            && self::MAX_PUBLIC_REQUESTS - $this->publicRequests >= 3;
    }

    /** @return array{total_live_http: int, yandex_requests: int, public_requests: int, timeweb_requests: int} */
    public function summary(): array
    {
        return [
            'total_live_http' => $this->totalRequests,
            'yandex_requests' => $this->yandexRequests,
            'public_requests' => $this->publicRequests,
            'timeweb_requests' => 0,
        ];
    }
}
