<?php

namespace Tests\Feature\AiSales;

use App\Models\AiAgentDefinition;
use App\Models\AiModelResidencyVerification;
use App\Models\User;

abstract class Stage04TestCase extends UnitContextsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.fake_execution_enabled' => true,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.local_ru_calls_enabled' => true,
            'ai-sales.external_sanitized_calls_enabled' => true,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
        ]);
    }

    protected function aiUser(array $lanes = ['sales'], array $extra = []): User
    {
        $lanePermissions = array_map(
            static fn (string $lane): string => "ai_sales.{$lane}.view",
            $lanes,
        );

        return $this->userWith(array_values(array_unique([
            'ai_sales.view',
            'ai_sales.control.view',
            'ai_sales.research.run',
            'ai_sales.runs.view',
            'ai_sales.runs.cancel',
            ...$lanePermissions,
            ...$extra,
        ])));
    }

    protected function enableDefinition(string $code): AiAgentDefinition
    {
        $definition = AiAgentDefinition::query()->where('code', $code)->firstOrFail();
        $definition->update(['enabled' => true]);

        return $definition->fresh();
    }

    protected function verifyLocalResidency(User $verifier): AiModelResidencyVerification
    {
        return AiModelResidencyVerification::query()->create([
            'provider_code' => 'fake',
            'provider_route' => 'local_ru',
            'model_id' => 'fake-local-ru-v1',
            'declared_contour' => 'local_ru',
            'declared_country' => 'RU',
            'evidence_reference' => 'synthetic:test-human-review',
            'evidence_hash' => hash('sha256', 'synthetic:test-human-review'),
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'expires_at' => now()->addDay(),
            'status' => 'verified',
            'probe_version' => 'stage04-test-v1',
        ]);
    }
}
