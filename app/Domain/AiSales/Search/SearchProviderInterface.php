<?php

namespace App\Domain\AiSales\Search;

interface SearchProviderInterface
{
    public function code(): string;

    /** @return list<string> */
    public function profiles(): array;

    public function search(SearchProviderRequest $request): SearchProviderResponse;
}
