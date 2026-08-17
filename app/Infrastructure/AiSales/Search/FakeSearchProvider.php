<?php

namespace App\Infrastructure\AiSales\Search;

use App\Domain\AiSales\Search\SearchProviderInterface;
use App\Domain\AiSales\Search\SearchProviderRequest;
use App\Domain\AiSales\Search\SearchProviderResponse;
use App\Domain\AiSales\Search\SearchProviderResult;
use App\Domain\AiSales\Search\SearchProviderUsage;
use App\Services\Yandex\YandexSearchProfileRegistry;

class FakeSearchProvider implements SearchProviderInterface
{
    /** @param null|list<array{title?: string, url: string, domain?: string, snippet?: string}> $fixtures */
    public function __construct(private readonly ?array $fixtures = null) {}

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
        $fixtures = $this->fixtures ?? [
            [
                'title' => 'ООО Синтетический покупатель',
                'url' => 'https://buyer.synthetic.example/about',
                'domain' => 'buyer.synthetic.example',
                'snippet' => 'Repository-owned fictional buyer discovery result.',
            ],
            [
                'title' => 'Синтетический каталог',
                'url' => 'https://catalog.synthetic.example/company',
                'domain' => 'catalog.synthetic.example',
                'snippet' => 'Repository-owned fictional public company listing.',
            ],
        ];

        $results = [];
        foreach (array_slice($fixtures, 0, $request->maxResults) as $index => $fixture) {
            $results[] = new SearchProviderResult(
                $index + 1,
                isset($fixture['title']) ? mb_substr($fixture['title'], 0, 512) : null,
                $fixture['url'],
                $fixture['domain'] ?? parse_url($fixture['url'], PHP_URL_HOST),
                isset($fixture['snippet']) ? mb_substr($fixture['snippet'], 0, 2000) : null,
            );
        }

        return new SearchProviderResponse(
            $this->code(),
            $request->profileCode,
            $results,
            new SearchProviderUsage(1, count($results)),
            'fake-search-'.substr($request->requestHash, 0, 16),
        );
    }
}
