<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiProviderModel;
use App\Models\AiProviderPricingSnapshot;
use App\Models\User;
use Throwable;

class TimewebPricingSnapshotService
{
    public const PERMISSION = 'ai_sales.pricing.verify';

    public function __construct(private readonly TimewebModelSelector $models) {}

    public function record(
        AiProviderRoute $route,
        string $modelId,
        int $verifierId,
        string $inputRate,
        string $outputRate,
        ?string $reasoningRate,
        string $sourceReference,
        string $sourceHash,
    ): AiProviderPricingSnapshot {
        if (! in_array(app()->environment(), ['local', 'testing', 'staging'], true)) {
            throw new PolicyViolation('timeweb_pricing_environment_blocked', 'Pricing evidence is forbidden outside local, testing and staging.');
        }

        $this->models->assertAllowed($route, $modelId);

        if (! AiProviderModel::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $route->value)
            ->where('model_id', $modelId)
            ->where('active_in_inventory', true)
            ->exists()) {
            throw new PolicyViolation('timeweb_pricing_inventory_missing', 'Exact model is absent from current Timeweb inventory.');
        }

        $verifier = User::query()->find($verifierId);

        if (! $verifier || ($verifier->status ?? 'active') !== 'active' || ! $this->permitted($verifier)) {
            throw new PolicyViolation('timeweb_pricing_verifier_forbidden', 'Active human verifier with the dedicated pricing permission is required.');
        }

        $version = config('ai-sales.providers.timeweb.probe.pricing_snapshot_version');

        if (! is_string($version) || $version === '' || mb_strlen($version) > 64
            || preg_match('/^[A-Za-z0-9._:-]+$/', $version) !== 1) {
            throw new PolicyViolation('timeweb_pricing_snapshot_missing', 'A bounded code-owned pricing snapshot version is required.');
        }

        $inputRate = $this->rate($inputRate);
        $outputRate = $this->rate($outputRate);
        $reasoningRate = $reasoningRate === null || trim($reasoningRate) === '' ? null : $this->rate($reasoningRate);
        $sourceReference = trim($sourceReference);

        if (mb_strlen($sourceReference) < 8 || mb_strlen($sourceReference) > 512
            || preg_match('/^(?:panel-review|support-ticket|contract-reference|public-doc):[A-Za-z0-9._:\/\-#]+$/', $sourceReference) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session/i', $sourceReference) === 1
            || $this->containsConfiguredKey($sourceReference)
            || preg_match('/^[a-f0-9]{64}$/', $sourceHash) !== 1) {
            throw new PolicyViolation('timeweb_pricing_evidence_invalid', 'Pricing requires a safe evidence reference and SHA-256 hash.');
        }

        $existing = AiProviderPricingSnapshot::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $route->value)
            ->where('model_id', $modelId)
            ->where('version', $version)
            ->first();

        if ($existing) {
            $same = $existing->input_per_million === $inputRate
                && $existing->output_per_million === $outputRate
                && $existing->reasoning_per_million === $reasoningRate
                && hash_equals($existing->source_hash, $sourceHash);

            if (! $same) {
                throw new PolicyViolation('timeweb_pricing_version_conflict', 'Immutable pricing version already exists with different evidence.');
            }

            return $existing;
        }

        return AiProviderPricingSnapshot::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => $route->value,
            'model_id' => $modelId,
            'version' => $version,
            'currency' => 'RUB',
            'input_per_million' => $inputRate,
            'output_per_million' => $outputRate,
            'reasoning_per_million' => $reasoningRate,
            'effective_at' => now(),
            'expires_at' => now()->addDays(30),
            'source_reference' => $sourceReference,
            'source_hash' => $sourceHash,
            'recorded_by_reference' => 'user:'.$verifier->id,
        ]);
    }

    private function rate(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,10})(?:\.(\d{1,6}))?$/', $value, $matches) !== 1) {
            throw new PolicyViolation('timeweb_pricing_rate_invalid', 'Pricing rates must be non-negative RUB decimals with at most six places.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 6, '0');

        if ($whole > 1_000_000 || ($whole === 1_000_000 && (int) $fraction > 0)) {
            throw new PolicyViolation('timeweb_pricing_rate_invalid', 'Pricing rate exceeds the Stage 05 arithmetic safety bound.');
        }

        return $matches[1].'.'.$fraction;
    }

    private function permitted(User $user): bool
    {
        try {
            return $user->hasRole('admin', 'crm') || $user->hasPermissionTo(self::PERMISSION, 'crm');
        } catch (Throwable) {
            return false;
        }
    }

    private function containsConfiguredKey(string $value): bool
    {
        foreach (AiProviderRoute::cases() as $route) {
            $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

            if (is_string($key) && strlen($key) >= 8 && str_contains($value, $key)) {
                return true;
            }
        }

        return false;
    }
}
