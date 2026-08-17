<?php

namespace App\Domain\AiSales\Probes;

use App\Domain\AiSales\Search\SearchProviderException;
use Psr\Http\Message\RequestInterface;

final class ExistingYandexProbeHttpGuard
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

        foreach (array_slice(array_values(array_unique($hosts)), 0, 3) as $host) {
            $host = mb_strtolower(rtrim(trim($host), '.'));
            if ($host === '' || preg_match('/^[a-z0-9.-]{1,253}$/', $host) !== 1) {
                throw new SearchProviderException('probe_policy', 'stage09b_public_host_invalid');
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

        if (! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || ($port !== null && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)))) {
            throw new SearchProviderException('probe_policy', 'stage09b_http_target_blocked');
        }
        if ($this->totalRequests >= self::MAX_TOTAL_REQUESTS) {
            throw new SearchProviderException('probe_budget', 'stage09b_total_http_budget_exceeded');
        }

        $configuredYandexHost = mb_strtolower((string) config('services.yandex_search.host'));
        if ($configuredYandexHost !== '' && hash_equals($configuredYandexHost, $host)) {
            if ($scheme !== 'https'
                || mb_strtoupper($request->getMethod()) !== 'POST'
                || $this->yandexRequests >= self::MAX_YANDEX_REQUESTS) {
                throw new SearchProviderException('probe_policy', 'stage09b_yandex_request_blocked');
            }

            $this->yandexRequests++;
            $this->totalRequests++;

            return $request;
        }

        if ($host === 'api.timeweb.ai') {
            throw new SearchProviderException('probe_policy', 'stage09b_timeweb_request_blocked');
        }
        if (! isset($this->allowedPublicHosts[$host])
            || mb_strtoupper($request->getMethod()) !== 'GET'
            || $this->publicRequests >= self::MAX_PUBLIC_REQUESTS) {
            throw new SearchProviderException('probe_policy', 'stage09b_public_request_blocked');
        }

        $this->publicRequests++;
        $this->totalRequests++;

        return $request;
    }

    public function canAttemptPage(): bool
    {
        // One robots request and up to two page requests (one redirect) must fit.
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
