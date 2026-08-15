<?php

namespace App\Domain\AiSales\DTO\Providers;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderRoute;

final readonly class ProviderSelectionDecision
{
    public string $decisionHash;

    public function __construct(
        public string $providerCode,
        public AiProviderRoute $route,
        public string $modelId,
        public AiProcessingContour $contour,
        public array $verifiedCapabilities,
        public string $reasonCode,
        public bool $fallbackAllowed = false,
    ) {
        $this->decisionHash = hash('sha256', json_encode([
            'provider' => $providerCode,
            'route' => $route->value,
            'model' => $modelId,
            'contour' => $contour->value,
            'capabilities' => array_values($verifiedCapabilities),
            'reason' => $reasonCode,
            'fallback_allowed' => $fallbackAllowed,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
