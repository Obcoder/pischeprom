<?php

namespace App\Domain\AiSales\Prospecting;

final readonly class CompanyIdentityEnvelope
{
    /** @param list<string> $sourceReferences */
    public function __construct(
        public string $registrableDomain,
        public ?string $workingName,
        public ?string $activitySummary,
        public ?string $geography,
        public int $confidence,
        public string $evidenceStatus,
        public array $sourceReferences,
    ) {}

    public function resolved(): bool
    {
        return $this->workingName !== null && $this->evidenceStatus === 'public_identity_observed';
    }
}
