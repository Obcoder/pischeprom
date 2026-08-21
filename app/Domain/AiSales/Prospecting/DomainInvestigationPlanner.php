<?php

namespace App\Domain\AiSales\Prospecting;

use App\Models\ProspectingSearchResult;
use Illuminate\Support\Collection;

final class DomainInvestigationPlanner
{
    public const VERSION = 'domain-investigation-v1';

    /**
     * Selects one best page per canonical domain before considering a second
     * page. Within each domain the deterministic order is homepage, company,
     * contacts, then relevant result rank.
     *
     * @param  Collection<int, ProspectingSearchResult>  $results
     * @return Collection<int, ProspectingSearchResult>
     */
    public function select(Collection $results, int $domainLimit, int $pageLimit): Collection
    {
        $groups = $results->groupBy('domain_hash')->take(max(0, $domainLimit))->map(
            fn (Collection $items): Collection => $items->sortBy(fn (ProspectingSearchResult $item): string => sprintf(
                '%d|%010d',
                $this->pagePriority((string) parse_url((string) $item->canonical_url, PHP_URL_PATH)),
                (int) $item->rank,
            ))->values(),
        );
        $selected = collect();
        for ($offset = 0; $selected->count() < $pageLimit; $offset++) {
            $added = false;
            foreach ($groups as $items) {
                if ($selected->count() >= $pageLimit) {
                    break;
                }
                if ($items->has($offset)) {
                    $selected->push($items[$offset]);
                    $added = true;
                }
            }
            if (! $added) {
                break;
            }
        }

        return $selected->values();
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
