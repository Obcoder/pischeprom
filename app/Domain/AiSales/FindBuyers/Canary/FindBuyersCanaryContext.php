<?php

namespace App\Domain\AiSales\FindBuyers\Canary;

use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchQuery;
use App\Models\User;

final readonly class FindBuyersCanaryContext
{
    /** @param array<string, int> $caps */
    public function __construct(
        public ProspectingSearchJob $job,
        public ProspectingSearchQuery $query,
        public User $operator,
        public string $productName,
        public string $launchSourceType,
        public int $originatingGoodCount,
        public array $caps,
    ) {}
}
