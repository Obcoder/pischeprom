<?php

namespace App\Domain\AiSales\DTO\Providers;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiResidencyVerificationStatus;
use Illuminate\Support\Carbon;

final readonly class AiResidencyVerification
{
    public function __construct(
        public string $providerCode,
        public string $providerRoute,
        public string $modelId,
        public AiProcessingContour $declaredContour,
        public string $declaredCountry,
        public AiResidencyVerificationStatus $status,
        public ?int $verifiedBy,
        public ?Carbon $verifiedAt,
        public ?Carbon $expiresAt,
        public ?string $probeVersion,
    ) {}

    public function current(): bool
    {
        return $this->status === AiResidencyVerificationStatus::Verified
            && $this->verifiedBy !== null
            && $this->verifiedAt !== null
            && $this->expiresAt !== null
            && $this->expiresAt->isFuture()
            && $this->declaredContour === AiProcessingContour::LocalRu
            && strtoupper($this->declaredCountry) === 'RU';
    }
}
