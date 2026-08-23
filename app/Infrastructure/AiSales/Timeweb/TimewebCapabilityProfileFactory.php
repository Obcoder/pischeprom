<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\ProviderCapabilityProfile;
use App\Domain\AiSales\Enums\AiCapabilitySupportStatus;
use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Models\AiProviderCapability;
use App\Models\AiProviderModel;
use Illuminate\Support\Carbon;
use Throwable;

class TimewebCapabilityProfileFactory
{
    public function make(AiProviderRoute $route, string $modelId): ProviderCapabilityProfile
    {
        try {
            $inventoryCurrent = AiProviderModel::query()
                ->where('provider_code', 'timeweb')
                ->where('provider_route', $route->value)
                ->where('model_id', $modelId)
                ->where('active_in_inventory', true)
                ->exists();
            $records = AiProviderCapability::query()
                ->where('provider_code', 'timeweb')
                ->where('provider_route', $route->value)
                ->where('model_id', $modelId)
                ->get();
        } catch (Throwable) {
            $inventoryCurrent = false;
            $records = collect();
        }

        $capabilities = [];
        $maxContext = 0;
        $maxOutput = 0;
        $verifiedAt = null;
        $expiresAt = null;

        if ($inventoryCurrent) {
            foreach ($records as $record) {
                $capabilities[$record->capability] = $record->support_state === AiCapabilitySupportStatus::Supported
                    ? $record->status
                    : AiCapabilityVerificationStatus::Unknown;
                $maxContext = max($maxContext, (int) $record->max_context_tokens);
                $maxOutput = max($maxOutput, (int) $record->max_output_tokens);
                $verifiedAt = $this->latest($verifiedAt, $record->verified_at);
                $expiresAt = $this->earliest($expiresAt, $record->expires_at);
            }
        }

        return new ProviderCapabilityProfile(
            'timeweb',
            $route,
            $modelId,
            $route->contour(),
            $capabilities,
            $maxContext,
            $maxOutput,
            $verifiedAt,
            $expiresAt,
        );
    }

    private function latest(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        if ($candidate === null) {
            return $current;
        }

        return $current === null || $candidate->isAfter($current) ? $candidate : $current;
    }

    private function earliest(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        if ($candidate === null) {
            return $current;
        }

        return $current === null || $candidate->isBefore($current) ? $candidate : $current;
    }
}
