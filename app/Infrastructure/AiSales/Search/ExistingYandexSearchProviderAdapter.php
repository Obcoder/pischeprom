<?php

namespace App\Infrastructure\AiSales\Search;

use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Search\SearchProviderInterface;
use App\Domain\AiSales\Search\SearchProviderRequest;
use App\Domain\AiSales\Search\SearchProviderResponse;
use App\Domain\AiSales\Search\SearchProviderResult;
use App\Domain\AiSales\Search\SearchProviderUsage;
use App\Services\Yandex\YandexSearchException;
use App\Services\Yandex\YandexSearchProfileRegistry;
use App\Services\YandexSearchService;

class ExistingYandexSearchProviderAdapter implements SearchProviderInterface
{
    public function __construct(private readonly YandexSearchService $service) {}

    public function code(): string
    {
        return 'existing_yandex';
    }

    public function profiles(): array
    {
        return [YandexSearchProfileRegistry::PROSPECTING];
    }

    public function search(SearchProviderRequest $request): SearchProviderResponse
    {
        if (! in_array($request->profileCode, $this->profiles(), true)) {
            throw new SearchProviderException('policy', 'search_profile_blocked');
        }

        $results = [];
        $requestCount = 0;
        $safeRequestId = null;
        $pages = (int) ceil($request->maxResults / 10);

        try {
            for ($page = 0; $page < $pages; $page++) {
                $response = $this->service->search($request->queryText, $page, $request->profileCode);
                $requestCount++;
                $safeRequestId ??= $response['requestId'] ?? null;
                $parsed = $this->service->parseXmlResults(
                    (string) ($response['rawData'] ?? ''),
                    $page * 10,
                    $request->profileCode,
                );

                if ($parsed === []) {
                    break;
                }

                foreach ($parsed as $item) {
                    if (count($results) >= $request->maxResults) {
                        break 2;
                    }
                    $results[] = new SearchProviderResult(
                        (int) $item['position'],
                        $item['title'],
                        (string) $item['url'],
                        $item['domain'],
                        $item['snippet'],
                    );
                }
            }
        } catch (YandexSearchException $exception) {
            throw new SearchProviderException($exception->category, $exception->safeCode);
        }

        return new SearchProviderResponse(
            $this->code(),
            $request->profileCode,
            $results,
            new SearchProviderUsage($requestCount, count($results)),
            $safeRequestId,
        );
    }
}
