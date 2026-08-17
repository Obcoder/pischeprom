<?php

namespace App\Domain\AiSales\Search;

use InvalidArgumentException;

final readonly class SearchProviderRequest
{
    public function __construct(
        public string $jobPublicId,
        public int $queryId,
        public string $profileCode,
        public string $queryText,
        public string $language,
        public ?string $geography,
        public int $maxResults,
        public string $requestHash,
    ) {
        if ($jobPublicId === '' || $queryId < 1) {
            throw new InvalidArgumentException('Search request identity is required.');
        }
        if ($profileCode !== 'prospecting_b2b_discovery') {
            throw new InvalidArgumentException('Only the code-owned prospecting search profile is accepted.');
        }
        if (trim($queryText) === '' || mb_strlen($queryText) > 512) {
            throw new InvalidArgumentException('Search query is empty or exceeds its bound.');
        }
        if (! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $language)) {
            throw new InvalidArgumentException('Search language is outside the allowlist format.');
        }
        if ($geography !== null && mb_strlen($geography) > 255) {
            throw new InvalidArgumentException('Search geography exceeds its bound.');
        }
        if ($maxResults < 1 || $maxResults > 50) {
            throw new InvalidArgumentException('Search result limit is outside its bounds.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $requestHash)) {
            throw new InvalidArgumentException('Search request hash must be SHA-256.');
        }
    }
}
