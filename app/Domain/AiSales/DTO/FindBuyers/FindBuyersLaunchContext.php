<?php

namespace App\Domain\AiSales\DTO\FindBuyers;

final readonly class FindBuyersLaunchContext
{
    public function __construct(
        public array $source,
        public ?array $primaryProduct,
        public array $productOptions,
        public ?array $originatingGood,
        public array $offerOptions,
        public array $recentJobs,
        public array $summaryCounts,
        public array $criteria,
        public array $geography,
        public array $eligibility,
        public array $disclosurePreview,
        public array $runtime,
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'primary_product' => $this->primaryProduct,
            'product_options' => $this->productOptions,
            'originating_good' => $this->originatingGood,
            'offer_options' => $this->offerOptions,
            'recent_jobs' => $this->recentJobs,
            'summary_counts' => $this->summaryCounts,
            'criteria' => $this->criteria,
            'geography' => $this->geography,
            'eligibility' => $this->eligibility,
            'disclosure_preview' => $this->disclosurePreview,
            'runtime' => $this->runtime,
        ];
    }
}
