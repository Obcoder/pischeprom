<?php

namespace App\Domain\AiSales\DTO\Providers;

use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderRoute;
use Illuminate\Support\Carbon;

final readonly class ProviderCapabilityProfile
{
    public function __construct(
        public string $providerCode,
        public AiProviderRoute $route,
        public string $modelId,
        public AiProcessingContour $contour,
        public array $capabilities,
        public int $maxContextTokens,
        public int $maxOutputTokens,
        public ?Carbon $verifiedAt,
        public ?Carbon $expiresAt,
    ) {}

    public function supports(AiRequestRequirements $requirements): bool
    {
        if ($this->route->contour() !== $this->contour || ($this->expiresAt && $this->expiresAt->isPast())) {
            return false;
        }

        if ($requirements->maxInputTokens > $this->maxContextTokens || $requirements->maxOutputTokens > $this->maxOutputTokens) {
            return false;
        }

        foreach ($requirements->capabilities as $capability) {
            $status = $this->capabilities[$capability] ?? AiCapabilityVerificationStatus::Unknown;

            if (is_string($status)) {
                $status = AiCapabilityVerificationStatus::tryFrom($status) ?? AiCapabilityVerificationStatus::Unknown;
            }

            if (! $status->verified()) {
                return false;
            }
        }

        return ! $requirements->requiresStoreFalse
            || (($this->capabilities['store_false'] ?? AiCapabilityVerificationStatus::Unknown) instanceof AiCapabilityVerificationStatus
                ? $this->capabilities['store_false']->verified()
                : (AiCapabilityVerificationStatus::tryFrom((string) ($this->capabilities['store_false'] ?? 'unknown'))?->verified() ?? false));
    }
}
