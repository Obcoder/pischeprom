<?php

namespace App\Domain\AiSales\DTO\Prospecting;

use Carbon\CarbonInterface;

final readonly class UnitDossierTimelineItem
{
    public function __construct(
        public string $type,
        public string $summary,
        public string $referenceType,
        public int|string $referenceId,
        public CarbonInterface $occurredAt,
    ) {}

    public function safeArray(): array
    {
        return [
            'type' => mb_substr($this->type, 0, 64),
            'summary' => mb_substr($this->summary, 0, 512),
            'reference' => [
                'type' => mb_substr($this->referenceType, 0, 64),
                'id' => $this->referenceId,
            ],
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
