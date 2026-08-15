<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiProviderPricingSnapshot;

class TimewebProbeCostEstimator
{
    public function maximum(AiProviderRoute $route, string $modelId, int $inputTokens, int $outputTokens): string
    {
        return $this->calculate($route, $modelId, $inputTokens, $outputTokens, 0, true);
    }

    public function actualOrReserved(
        AiProviderRoute $route,
        string $modelId,
        AiProviderUsage $usage,
        string $reserved,
    ): string {
        if ($usage->inputTokens === null || $usage->outputTokens === null) {
            return $reserved;
        }

        return $this->calculate(
            $route,
            $modelId,
            $usage->inputTokens,
            $usage->outputTokens,
            $usage->reasoningTokens ?? 0,
        );
    }

    private function calculate(
        AiProviderRoute $route,
        string $modelId,
        int $inputTokens,
        int $outputTokens,
        int $reasoningTokens,
        bool $worstCaseReasoning = false,
    ): string {
        if ($inputTokens < 0 || $inputTokens > 100_000
            || $outputTokens < 0 || $outputTokens > 20_000
            || $reasoningTokens < 0 || $reasoningTokens > $outputTokens) {
            throw new PolicyViolation('timeweb_pricing_token_bounds_invalid', 'Pricing estimation requires bounded, internally consistent token counts.');
        }

        $version = config('ai-sales.providers.timeweb.probe.pricing_snapshot_version');

        if (! is_string($version) || $version === '' || mb_strlen($version) > 64) {
            throw new PolicyViolation('timeweb_pricing_snapshot_missing', 'A code-owned pricing snapshot version is required before model probes.');
        }

        $snapshot = AiProviderPricingSnapshot::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $route->value)
            ->where('model_id', $modelId)
            ->where('version', $version)
            ->where('currency', 'RUB')
            ->where('effective_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (! $snapshot || ! preg_match('/^[a-f0-9]{64}$/', (string) $snapshot->source_hash)) {
            throw new PolicyViolation('timeweb_pricing_snapshot_unverified', 'The exact model has no current evidence-backed RUB pricing snapshot.');
        }

        $inputRate = $this->millionths((string) $snapshot->input_per_million);
        $outputRate = $this->millionths((string) $snapshot->output_per_million);
        $reasoningRate = $snapshot->reasoning_per_million === null
            ? $outputRate
            : $this->millionths((string) $snapshot->reasoning_per_million);

        if ($worstCaseReasoning && $reasoningRate > $outputRate) {
            $reasoningTokens = $outputTokens;
        }

        $regularOutputTokens = max(0, $outputTokens - $reasoningTokens);
        $rubMillionths = (int) ceil((
            ($inputTokens * $inputRate)
            + ($regularOutputTokens * $outputRate)
            + ($reasoningTokens * $reasoningRate)
        ) / 1_000_000);
        $rubTenThousandths = (int) ceil($rubMillionths / 100);

        return number_format($rubTenThousandths / 10_000, 4, '.', '');
    }

    private function millionths(string $value): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,6}))?$/', $value, $matches) !== 1) {
            throw new PolicyViolation('timeweb_pricing_snapshot_invalid', 'Pricing snapshot contains an invalid non-negative rate.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 6, '0');

        if ($whole > 1_000_000 || ($whole === 1_000_000 && (int) $fraction > 0)) {
            throw new PolicyViolation('timeweb_pricing_snapshot_invalid', 'Pricing snapshot rate exceeds the Stage 05 arithmetic safety bound.');
        }

        return ($whole * 1_000_000) + (int) $fraction;
    }
}
