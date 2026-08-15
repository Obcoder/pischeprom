<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiProviderCapability;

class AiProviderCapabilityAuthorizationService
{
    public function authorize(
        string $providerCode,
        AiProviderRoute $route,
        string $modelId,
        AiProcessingContour $contour,
        AiRequestRequirements $requirements,
    ): void {
        if ($route->contour() !== $contour) {
            throw new PolicyViolation('provider_capability_contour_mismatch', 'Capability verification cannot change the selected contour.');
        }

        $required = array_values(array_unique([
            ...$requirements->capabilities,
            ...($requirements->requiresStoreFalse ? ['store_false'] : []),
        ]));

        if ($required === []) {
            throw new PolicyViolation('provider_capability_unverified', 'At least one verified capability is required.');
        }

        $records = AiProviderCapability::query()
            ->where('provider_code', $providerCode)
            ->where('provider_route', $route->value)
            ->where('model_id', $modelId)
            ->whereIn('capability', $required)
            ->get()
            ->keyBy('capability');

        foreach ($required as $capability) {
            $record = $records->get($capability);

            if (! $record
                || $record->contour !== $contour
                || ! $record->status->verified()
                || $record->verified_at === null
                || ($record->expires_at !== null && ! $record->expires_at->isFuture())
                || ! is_string($record->evidence_hash)
                || ! preg_match('/^[a-f0-9]{64}$/', $record->evidence_hash)
                || (int) $record->max_context_tokens < $requirements->maxInputTokens
                || (int) $record->max_output_tokens < $requirements->maxOutputTokens) {
                throw new PolicyViolation(
                    'provider_capability_unverified',
                    'Required provider/model capability has no current verified evidence.',
                );
            }
        }
    }
}
