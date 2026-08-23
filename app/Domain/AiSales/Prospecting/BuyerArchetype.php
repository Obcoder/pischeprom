<?php

namespace App\Domain\AiSales\Prospecting;

final readonly class BuyerArchetype
{
    /**
     * @param  list<string>  $discoveryPhrases
     * @param  list<string>  $signals
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $segmentCode,
        public string $segmentLabel,
        public array $discoveryPhrases,
        public array $signals,
    ) {}

    /** @return array<string, mixed> */
    public function hashPayload(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'segment_code' => $this->segmentCode,
            'segment_label' => $this->segmentLabel,
            'discovery_phrases' => $this->discoveryPhrases,
            'signals' => $this->signals,
        ];
    }
}
