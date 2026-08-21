<?php

namespace App\Domain\AiSales\Prospecting;

final readonly class ResultBusinessRoleDecision
{
    /** @param list<string> $reasonCodes */
    public function __construct(
        public ResultBusinessRole $role,
        public array $reasonCodes,
        public int $confidence,
        public bool $researchEligible,
        public bool $candidateEligible,
    ) {}

    /** @return array<string, mixed> */
    public function safeArray(): array
    {
        return [
            'role' => $this->role->value,
            'reason_codes' => $this->reasonCodes,
            'confidence' => $this->confidence,
            'research_eligible' => $this->researchEligible,
            'candidate_eligible' => $this->candidateEligible,
        ];
    }
}
