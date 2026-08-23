<?php

namespace App\Domain\AiSales\DTO\Prospecting;

use App\Domain\AiSales\Enums\CandidateResolutionOutcome;

final readonly class CandidateResolutionDecision
{
    public const RULES_VERSION = 'stage08-deterministic-v1';

    public function __construct(
        public CandidateResolutionOutcome $outcome,
        public array $signalCodes,
        public array $matchedUnitIds,
        public array $evidenceReferences,
        public array $confidenceComponents,
        public bool $humanReviewRequired = true,
    ) {}

    public function rulesHash(): string
    {
        return hash('sha256', self::RULES_VERSION);
    }

    public function safeArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'signal_codes' => array_values($this->signalCodes),
            'matched_unit_ids' => array_map('intval', $this->matchedUnitIds),
            'evidence_references' => array_map(static fn ($value) => mb_substr((string) $value, 0, 512), $this->evidenceReferences),
            'confidence_components' => $this->confidenceComponents,
            'rules_version' => self::RULES_VERSION,
            'rules_hash' => $this->rulesHash(),
            'human_review_required' => $this->humanReviewRequired,
        ];
    }
}
