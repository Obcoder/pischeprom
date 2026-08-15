<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiModelResidencyVerification;
use App\Models\AiProviderModel;
use App\Models\User;
use Throwable;

class TimewebResidencyVerificationService
{
    public const PERMISSION = 'ai_sales.residency.verify';

    public function __construct(private readonly TimewebModelSelector $models) {}

    public function verify(
        string $modelId,
        int $verifierId,
        string $evidenceReference,
        string $evidenceHash,
    ): AiModelResidencyVerification {
        if (! in_array(app()->environment(), ['local', 'testing', 'staging'], true)) {
            throw new PolicyViolation('timeweb_residency_environment_blocked', 'Residency verification is forbidden outside local, testing and staging.');
        }

        $this->models->assertAllowed(AiProviderRoute::LocalRu, $modelId);

        if (! AiProviderModel::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', AiProviderRoute::LocalRu->value)
            ->where('model_id', $modelId)
            ->where('active_in_inventory', true)
            ->exists()) {
            throw new PolicyViolation('timeweb_residency_inventory_missing', 'Exact local model is absent from current Timeweb inventory.');
        }

        $verifier = User::query()->find($verifierId);

        if (! $verifier || ($verifier->status ?? 'active') !== 'active' || ! $this->permitted($verifier)) {
            throw new PolicyViolation('timeweb_residency_verifier_forbidden', 'Active human verifier with the dedicated permission is required.');
        }

        $evidenceReference = trim($evidenceReference);

        if (mb_strlen($evidenceReference) < 8 || mb_strlen($evidenceReference) > 512
            || preg_match('/^(?:panel-review|support-ticket|contract-reference|public-doc):[A-Za-z0-9._:\/\-#]+$/', $evidenceReference) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session/i', $evidenceReference) === 1
            || $this->containsConfiguredKey($evidenceReference)
            || preg_match('/^[a-f0-9]{64}$/', $evidenceHash) !== 1) {
            throw new PolicyViolation('timeweb_residency_evidence_invalid', 'Residency evidence must be a safe reference plus a SHA-256 hash, never a raw document or secret.');
        }

        $expiryDays = min(30, max(1, (int) config('ai-sales.providers.timeweb.probe.residency_expiry_days', 30)));

        return AiModelResidencyVerification::query()->updateOrCreate([
            'provider_code' => 'timeweb',
            'provider_route' => AiProviderRoute::LocalRu->value,
            'model_id' => $modelId,
        ], [
            'declared_contour' => 'local_ru',
            'declared_country' => 'RU',
            'evidence_reference' => $evidenceReference,
            'evidence_hash' => $evidenceHash,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'expires_at' => now()->addDays($expiryDays),
            'status' => 'verified',
            'probe_version' => 'timeweb-stage05-human-v1',
            'notes' => 'Human confirmed exact model residency from an approved safe evidence reference.',
        ]);
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
