<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Support\AiCanonicalJson;

final readonly class ScoringInput
{
    public string $inputHash;

    public string $evidenceHash;

    public function __construct(
        public string $level,
        public array $subject,
        public array $signals,
        public array $evidence,
    ) {
        $this->inputHash = AiCanonicalJson::hash([
            'level' => $level,
            'subject' => $subject,
            'signals' => $signals,
            'evidence' => $evidence,
        ]);
        $this->evidenceHash = AiCanonicalJson::hash(['evidence' => $evidence]);
    }
}
