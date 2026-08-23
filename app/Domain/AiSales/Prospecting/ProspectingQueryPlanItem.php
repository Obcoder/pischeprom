<?php

namespace App\Domain\AiSales\Prospecting;

final readonly class ProspectingQueryPlanItem
{
    public function __construct(
        public int $sequence,
        public int $productId,
        public string $templateCode,
        public string $templateVersion,
        public string $templateHash,
        public string $queryText,
        public string $queryHash,
        public string $language,
        public ?string $geography,
        public string $industryIntent,
    ) {}

    public function hashPayload(): array
    {
        return [
            'sequence' => $this->sequence,
            'product_id' => $this->productId,
            'template_code' => $this->templateCode,
            'template_version' => $this->templateVersion,
            'template_hash' => $this->templateHash,
            'query_hash' => $this->queryHash,
            'language' => $this->language,
            'geography' => $this->geography,
            'industry_intent' => $this->industryIntent,
        ];
    }
}
