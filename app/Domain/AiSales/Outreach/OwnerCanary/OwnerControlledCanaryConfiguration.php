<?php

namespace App\Domain\AiSales\Outreach\OwnerCanary;

use Carbon\CarbonImmutable;

final readonly class OwnerControlledCanaryConfiguration
{
    public function __construct(
        public string $environment,
        public string $recipient,
        public string $recipientHmacSuffix,
        public string $providerKeyHmacSuffix,
        public string $permissionEvidenceReference,
        public string $permissionEvidenceSha256,
        public string $securityEvidenceReference,
        public string $securityEvidenceSha256,
        public CarbonImmutable $securityVerifiedAt,
        public string $callbackHost,
        public string $callbackPath,
        public string $providerHost,
        public string $webhookQueueConnection,
    ) {}
}
