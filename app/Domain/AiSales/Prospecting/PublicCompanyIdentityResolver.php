<?php

namespace App\Domain\AiSales\Prospecting;

use App\Models\ProspectingSearchResult;
use Illuminate\Support\Collection;

final class PublicCompanyIdentityResolver
{
    public const VERSION = 'public-company-identity-v1';

    /** @param Collection<int, ProspectingSearchResult> $results */
    public function resolve(Collection $results): CompanyIdentityEnvelope
    {
        $first = $results->first();
        $domain = (string) $first?->registrable_domain;
        $ordered = $results->sortBy(fn (ProspectingSearchResult $result): string => sprintf(
            '%d|%010d',
            $this->pagePriority((string) parse_url((string) $result->canonical_url, PHP_URL_PATH)),
            (int) $result->rank,
        ));
        $name = null;
        $references = [];
        foreach ($ordered as $result) {
            $references[] = 'search-result:'.$result->result_hash;
            $pagePriority = $this->pagePriority((string) parse_url((string) $result->canonical_url, PHP_URL_PATH));
            foreach ([$result->publicFetch?->page_title, $result->title] as $title) {
                $candidate = $this->companyLikeName((string) $title, $pagePriority <= 1);
                if ($candidate !== null) {
                    $name = $candidate;
                    break 2;
                }
            }
        }
        $research = $ordered->pluck('research')->filter(fn ($record) => $record?->status === 'completed' && $record?->schema_valid);
        $activity = $research->pluck('safe_summary')->filter()->first()
            ?: $ordered->pluck('publicFetch.meta_description')->filter()->first();
        $geography = $research->flatMap(fn ($record) => (array) $record->location_hints)->filter()->first()
            ?: $first?->searchQuery?->geography;

        return new CompanyIdentityEnvelope(
            $domain,
            $name,
            filled($activity) ? mb_substr(trim((string) $activity), 0, 1000) : null,
            filled($geography) ? mb_substr(trim((string) $geography), 0, 255) : null,
            $name === null ? 25 : 75,
            $name === null ? 'identity_unresolved' : 'public_identity_observed',
            array_values(array_unique($references)),
        );
    }

    private function companyLikeName(string $title, bool $identityPage): ?string
    {
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?? '');
        if ($title === '' || mb_strlen($title) < 2 || mb_strlen($title) > 160) {
            return null;
        }
        $lower = mb_strtolower($title);
        foreach (['купить', 'цена', 'доставка', 'оптом', 'заказать', 'каталог', 'рецепт', 'статья', 'маркетплейс'] as $commercial) {
            if (str_contains($lower, $commercial)) {
                return null;
            }
        }
        $hasBrandDelimiter = preg_match('/\s+[|—–-]\s+/u', $title) === 1;
        $candidate = trim((string) preg_split('/\s+[|—–-]\s+/u', $title, 2)[0], " \t\n\r\0\x0B\"'«»");
        if ($candidate === '' || mb_strlen($candidate) > 100 || count(preg_split('/\s+/u', $candidate) ?: []) > 10) {
            return null;
        }
        if (! $identityPage && ! $hasBrandDelimiter && preg_match('/\b(ооо|ао|пао|компания|завод|фабрика|комбинат)\b/ui', $candidate) !== 1) {
            return null;
        }

        return $candidate;
    }

    private function pagePriority(string $path): int
    {
        $path = mb_strtolower(trim($path, '/'));
        if ($path === '') {
            return 0;
        }
        if (preg_match('~(^|/)(about|company|o-kompanii|о-компании)(/|$)~u', $path)) {
            return 1;
        }
        if (preg_match('~(^|/)(contact|contacts|kontakty|контакты)(/|$)~u', $path)) {
            return 2;
        }

        return 3;
    }
}
