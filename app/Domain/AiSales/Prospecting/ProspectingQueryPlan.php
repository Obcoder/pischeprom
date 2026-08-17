<?php

namespace App\Domain\AiSales\Prospecting;

final readonly class ProspectingQueryPlan
{
    /** @var list<ProspectingQueryPlanItem> */
    public array $items;

    /** @param list<ProspectingQueryPlanItem> $items */
    public function __construct(
        public int $jobId,
        public string $productScopeHash,
        public string $registryHash,
        public string $planHash,
        array $items,
    ) {
        $this->items = array_values($items);
    }
}
