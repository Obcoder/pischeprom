<?php

namespace App\Domain\AiSales\Search;

use InvalidArgumentException;

final readonly class SearchProviderResponse
{
    /** @var list<SearchProviderResult> */
    public array $results;

    /** @param list<SearchProviderResult> $results */
    public function __construct(
        public string $providerCode,
        public string $profileCode,
        array $results,
        public SearchProviderUsage $usage,
        public ?string $safeRequestId = null,
    ) {
        foreach ($results as $result) {
            if (! $result instanceof SearchProviderResult) {
                throw new InvalidArgumentException('Search responses require normalized result DTOs.');
            }
        }
        if ($safeRequestId !== null && ! preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $safeRequestId)) {
            throw new InvalidArgumentException('Provider request ID is not safe to retain.');
        }
        $this->results = array_values($results);
    }
}
