<?php

namespace App\Domain\AiSales\DTO\FindBuyers;

use App\Models\ProspectingSearchJob;

final readonly class FindBuyersDraftResult
{
    public function __construct(
        public ProspectingSearchJob $job,
        public bool $created,
    ) {}
}
