<?php

namespace App\Domain\Banking\DTO;

use App\Models\Sale;

final readonly class MatchCandidate
{
    public function __construct(
        public Sale $sale,
        public int $score,
        public array $rules,
        public bool $automaticEligible,
        public string $outstandingAmount,
    ) {}
}
