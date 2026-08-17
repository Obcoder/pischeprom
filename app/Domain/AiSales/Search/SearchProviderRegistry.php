<?php

namespace App\Domain\AiSales\Search;

use LogicException;

class SearchProviderRegistry
{
    /** @var array<string, SearchProviderInterface> */
    private array $providers = [];

    /** @param list<SearchProviderInterface> $providers */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public function register(SearchProviderInterface $provider): void
    {
        if (isset($this->providers[$provider->code()])) {
            throw new LogicException("Search provider {$provider->code()} is already registered.");
        }

        $this->providers[$provider->code()] = $provider;
    }

    public function get(string $code): SearchProviderInterface
    {
        return $this->providers[$code]
            ?? throw new SearchProviderException('configuration', 'search_provider_not_registered');
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->providers);
    }
}
