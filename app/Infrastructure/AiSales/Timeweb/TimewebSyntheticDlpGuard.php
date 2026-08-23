<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryContract;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;

class TimewebSyntheticDlpGuard
{
    public function __construct(
        private readonly TimewebSyntheticFixtureRegistry $fixtures,
        private readonly DeterministicAiPayloadScanner $scanner,
        private readonly OutreachCanaryContract $outreachCanary,
    ) {}

    public function authorize(AiProviderRequest $request): string
    {
        $route = match ($request->contour) {
            AiProcessingContour::LocalRu => \App\Domain\AiSales\Enums\AiProviderRoute::LocalRu,
            AiProcessingContour::ExternalSanitized => \App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized,
            AiProcessingContour::None => throw new PolicyViolation('timeweb_contour_blocked', 'NONE cannot reach Timeweb transport.'),
        };

        if (! $request->syntheticOnly) {
            throw new PolicyViolation('timeweb_domain_runtime_blocked', 'Stage 05 Timeweb adapters accept repository-owned synthetic requests only.');
        }

        if ($this->outreachCanary->authorizeRequest($request)) {
            $fixtureCode = OutreachCanaryContract::SCENARIO;
        } else {
            $fixtureCode = $this->fixtures->codeForRequest($route, $request->inputItems, $request->sanitizedPayloadHash);

            if ($fixtureCode === null) {
                throw new PolicyViolation('timeweb_fixture_hash_blocked', 'Request payload hash is not a repository-owned synthetic fixture.');
            }

            if ($request->runPublicId !== '05000000-0000-4000-8000-000000000001'
                || $request->stepSequence !== 1
                || $request->responseSchemaName !== 'synthetic_probe_result'
                || $request->responseSchema !== $this->fixtures->responseSchema()
                || ! hash_equals(hash('sha256', 'stage05:'.$route->value.':'.$fixtureCode), $request->idempotencyKey)
                || ! hash_equals(hash('sha256', 'stage05:synthetic-policy:'.$route->value), $request->policyDecisionHash)
                || ! hash_equals(hash('sha256', 'stage05:synthetic-prompt:v1'), $request->promptHash)
                || ! hash_equals(hash('sha256', json_encode($request->responseSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), $request->schemaHash)
                || $request->classificationSummary !== $this->fixtures->classificationSummary($fixtureCode)
                || $request->containsLocalOnlyData !== $this->fixtures->containsLocalOnlyData($fixtureCode)
                || ($request->toolSchemas !== [] && $request->toolSchemas !== [$this->fixtures->toolSchema()])
                || ! $request->requirements->requiresStoreFalse) {
                throw new PolicyViolation('timeweb_synthetic_envelope_blocked', 'Synthetic request envelope differs from the fixed repository-owned contract.');
            }
        }

        if ($route === \App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized && $request->containsLocalOnlyData) {
            throw new PolicyViolation('timeweb_external_local_only_blocked', 'External Timeweb route cannot receive local-only data.');
        }

        foreach ($request->classificationSummary as $classification => $count) {
            if (! in_array($classification, ['public', 'personal_data'], true)
                || ! is_int($count) || $count < 0
                || ($route === \App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized && $classification !== 'public')) {
                throw new PolicyViolation('timeweb_classification_blocked', 'Synthetic request classification is not allowed for this contour.');
            }
        }

        foreach ($request->inputItems as $item) {
            $scan = $this->scanner->scan($item->data, $request->contour);

            if ($scan->secretCount > 0 || ($route === \App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized && $scan->personalDataCount > 0)) {
                throw new PolicyViolation('timeweb_dlp_blocked', 'Synthetic DLP canary was blocked before transport.');
            }

            $this->assertNoSpecialMarkers($item->data);
        }

        return $fixtureCode;
    }

    private function assertNoSpecialMarkers(array $payload): void
    {
        foreach ($payload as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if ($normalizedKey === 'no_raw_correspondence' && $value === true) {
                continue;
            }

            if (str_contains($normalizedKey, 'unclassified')
                || str_contains($normalizedKey, 'raw_correspondence')
                || str_contains($normalizedKey, 'supplier_secret')
                || str_contains($normalizedKey, 'customer_secret')) {
                throw new PolicyViolation('timeweb_dlp_blocked', 'Unclassified or raw correspondence marker was blocked before transport.');
            }

            if (is_array($value)) {
                $this->assertNoSpecialMarkers($value);

                continue;
            }

            if (is_string($value)
                && preg_match('/(?:supplier|customer)[_\s-]*secret|raw[_\s-]*correspondence|unclassified/i', $value) === 1) {
                throw new PolicyViolation('timeweb_dlp_blocked', 'Cross-lane/raw/unclassified canary was blocked before transport.');
            }
        }
    }
}
