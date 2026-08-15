<?php

namespace App\Domain\AiSales\Contracts;

use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\DTO\Providers\ProviderCapabilityProfile;
use App\Domain\AiSales\DTO\Providers\ProviderHealthResult;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;

interface AiProviderInterface
{
    public function code(): string;

    public function route(): AiProviderRoute;

    public function capabilities(AiModelProfile $modelProfile): ProviderCapabilityProfile;

    public function supports(AiRequestRequirements $requirements): bool;

    public function healthCheck(): ProviderHealthResult;

    public function createResponse(AiProviderRequest $request): AiProviderResponse;
}
