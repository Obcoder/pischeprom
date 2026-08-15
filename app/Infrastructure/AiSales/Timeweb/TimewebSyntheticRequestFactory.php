<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;

class TimewebSyntheticRequestFactory
{
    public function __construct(private readonly TimewebSyntheticFixtureRegistry $fixtures) {}

    public function make(
        AiProviderRoute $route,
        AiModelProfile $modelProfile,
        string $fixtureCode,
        array $capabilities,
        int $maxInputTokens,
        int $maxOutputTokens,
        array $toolSchemas = [],
        array $additionalItems = [],
    ): AiProviderRequest {
        $fixture = $this->fixtures->data($fixtureCode);
        $hash = $this->fixtures->hash($fixtureCode);
        $this->fixtures->assertAllowed($fixtureCode, $route, $hash);
        $schema = $this->fixtures->responseSchema();
        $items = [
            new AiProviderInputItem('instruction', 'stage05_synthetic_instruction', $this->fixtures->instructionData()),
            new AiProviderInputItem('sanitized_data', $fixtureCode, $fixture),
            ...$additionalItems,
        ];

        return new AiProviderRequest(
            '05000000-0000-4000-8000-000000000001',
            1,
            $route->contour(),
            $modelProfile,
            $items,
            $schema,
            $toolSchemas,
            new AiRequestRequirements($capabilities, $maxInputTokens, $maxOutputTokens, true),
            hash('sha256', 'stage05:'.$route->value.':'.$fixtureCode),
            hash('sha256', 'stage05:synthetic-policy:'.$route->value),
            hash('sha256', 'stage05:synthetic-prompt:v1'),
            hash('sha256', json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $this->fixtures->requestPayloadHash($items),
            $this->fixtures->classificationSummary($fixtureCode),
            $this->fixtures->containsLocalOnlyData($fixtureCode),
            min(60, max(1, (int) config('ai-sales.providers.timeweb.timeout_seconds', 45))),
            true,
        );
    }
}
