<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Search\SearchProviderResult;

class SearchResultDeduplicator
{
    public function __construct(
        private readonly PublicUrlNormalizer $urls,
        private readonly RegistrableDomainResolver $domains,
    ) {}

    /**
     * @param  list<SearchProviderResult>  $results
     * @return array{accepted: list<array<string, mixed>>, blocked_count: int}
     */
    public function normalize(array $results, int $limit): array
    {
        $accepted = [];
        $blocked = 0;
        $seenHashes = [];

        foreach (array_slice($results, 0, $limit) as $index => $result) {
            try {
                $canonicalUrl = $this->urls->normalize($result->url);
                $host = $this->urls->host($canonicalUrl);
                $domain = $this->domains->resolve($host);
            } catch (\Throwable) {
                $blocked++;

                continue;
            }
            $urlHash = hash('sha256', $canonicalUrl);
            $resultHash = hash('sha256', implode('|', [
                $urlHash,
                mb_strtolower(trim((string) $result->title)),
                mb_strtolower(trim((string) $result->snippet)),
            ]));
            $accepted[] = [
                'rank' => $index + 1,
                'result_type' => $result->resultType === 'organic' ? 'organic' : 'other',
                'title' => filled($result->title) ? mb_substr(trim((string) $result->title), 0, 512) : null,
                'snippet' => filled($result->snippet) ? mb_substr(trim((string) $result->snippet), 0, 2000) : null,
                'url' => $canonicalUrl,
                'canonical_url' => $canonicalUrl,
                'url_hash' => $urlHash,
                'registrable_domain' => $domain,
                'domain_hash' => hash('sha256', $domain),
                'result_hash' => $resultHash,
                'duplicate_of_result_hash' => $seenHashes[$urlHash] ?? null,
            ];
            $seenHashes[$urlHash] ??= $resultHash;
        }

        return ['accepted' => $accepted, 'blocked_count' => $blocked];
    }
}
