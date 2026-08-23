<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\Providers\AiResidencyVerification;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiModelResidencyVerification;
use App\Models\AiProviderModel;

class AiResidencyAuthorizationService
{
    public function find(string $providerCode, string $providerRoute, string $modelId): ?AiResidencyVerification
    {
        $record = AiModelResidencyVerification::query()
            ->where('provider_code', $providerCode)
            ->where('provider_route', $providerRoute)
            ->where('model_id', $modelId)
            ->first();

        if (! $record) {
            return null;
        }

        return new AiResidencyVerification(
            $record->provider_code,
            $record->provider_route,
            $record->model_id,
            $record->declared_contour,
            $record->declared_country,
            $record->status,
            $record->verified_by,
            $record->verified_at,
            $record->expires_at,
            $record->probe_version,
        );
    }

    public function authorize(string $providerCode, string $providerRoute, string $modelId): AiResidencyVerification
    {
        $verification = $this->find($providerCode, $providerRoute, $modelId);

        if ($providerCode === 'timeweb' && ! AiProviderModel::query()
            ->where('provider_code', $providerCode)
            ->where('provider_route', $providerRoute)
            ->where('model_id', $modelId)
            ->where('active_in_inventory', true)
            ->exists()) {
            throw new PolicyViolation(
                'residency_inventory_missing',
                'LOCAL_RU exact model must be present in the current Timeweb inventory.',
            );
        }

        if (! $verification?->current()) {
            throw new PolicyViolation(
                'residency_unverified',
                'LOCAL_RU requires a current human-verified RU residency record for the exact provider route and model.',
            );
        }

        return $verification;
    }
}
