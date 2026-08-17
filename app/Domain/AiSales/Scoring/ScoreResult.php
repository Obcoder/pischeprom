<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\ScoreBand;
use App\Domain\AiSales\Enums\ScoreEligibility;
use App\Domain\AiSales\Enums\ScoreReviewStatus;

final readonly class ScoreResult
{
    /** @param list<ScoreFactorResult> $factors */
    public function __construct(
        public ScoringDefinition $definition,
        public ScoringInput $input,
        public int $computedScore,
        public int $effectiveScore,
        public int $confidence,
        public ScoreBand $band,
        public ScoreEligibility $eligibility,
        public ScoreReviewStatus $reviewStatus,
        public string $nextBestAction,
        public array $factors,
    ) {}

    public function safeArray(): array
    {
        return [
            'computed_score' => $this->computedScore,
            'effective_score' => $this->effectiveScore,
            'confidence' => $this->confidence,
            'band' => $this->band->value,
            'eligibility' => $this->eligibility->value,
            'review_status' => $this->reviewStatus->value,
            'next_best_action' => $this->nextBestAction,
            'definition' => ['code' => $this->definition->code, 'version' => $this->definition->version, 'hash' => $this->definition->hash],
            'input_hash' => $this->input->inputHash,
            'evidence_hash' => $this->input->evidenceHash,
            'factors' => array_map(static fn (ScoreFactorResult $factor): array => $factor->safeArray(), $this->factors),
        ];
    }
}
