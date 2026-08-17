<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\ScoreFactorStatus;

final readonly class ScoreFactorResult
{
    public function __construct(
        public string $code,
        public string $polarity,
        public string $normalizedState,
        public int $weight,
        public int $contribution,
        public int $confidence,
        public ScoreFactorStatus $status,
        public string $safeRationale,
        public ?string $evidenceType = null,
        public ?string $evidenceReference = null,
        public ?string $evidenceHash = null,
        public ?string $evidenceAt = null,
    ) {}

    public function safeArray(): array
    {
        return [
            'factor_code' => $this->code,
            'polarity' => $this->polarity,
            'normalized_state' => $this->normalizedState,
            'weight' => $this->weight,
            'contribution' => $this->contribution,
            'confidence' => $this->confidence,
            'status' => $this->status->value,
            'safe_rationale' => mb_substr($this->safeRationale, 0, 1000),
            'evidence_type' => $this->evidenceType,
            'evidence_reference' => $this->evidenceReference ? mb_substr($this->evidenceReference, 0, 512) : null,
            'evidence_hash' => $this->evidenceHash,
            'evidence_at' => $this->evidenceAt,
        ];
    }
}
