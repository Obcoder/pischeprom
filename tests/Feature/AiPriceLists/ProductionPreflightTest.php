<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ProductionPreflightTest extends AiPriceListTestCase
{
    public function test_explicit_provider_probes_cannot_override_disabled_ai(): void
    {
        config()->set('ai-price-lists.ai.enabled', false);
        $ai = Mockery::mock(StructuredTextModelProviderInterface::class);
        $ai->shouldNotReceive('configured', 'generate');
        $ocr = Mockery::mock(OcrProviderInterface::class);
        $ocr->shouldNotReceive('configured', 'recognize');
        $this->app->instance(StructuredTextModelProviderInterface::class, $ai);
        $this->app->instance(OcrProviderInterface::class, $ocr);

        $this->artisan('price-lists:production-preflight', ['--ai' => true, '--vision' => true])->assertFailed();
        $this->assertDatabaseCount('ai_usage_records', 0);
    }

    public function test_local_preflight_uses_only_synthetic_data_and_cleans_storage(): void
    {
        $this->app->instance(StructuredTextModelProviderInterface::class, new class implements StructuredTextModelProviderInterface
        {
            public function configured(): bool
            {
                return true;
            }

            public function generate(StructuredModelRequest $request): StructuredModelResponse
            {
                return new StructuredModelResponse(
                    data: ['ok' => true],
                    model: 'fake-preflight',
                    externalRequestId: 'fake-preflight-request',
                    inputTokens: 1,
                    outputTokens: 1,
                    totalTokens: 2,
                    latencyMs: 1,
                );
            }
        });

        $this->artisan('price-lists:production-preflight', [
            '--schema' => true,
            '--storage' => true,
            '--scanner' => true,
            '--ai' => true,
            '--vision' => true,
        ])->assertSuccessful();

        $this->assertSame([], Storage::disk('local')->allFiles('supplier-price-lists-test/preflight'));
    }
}
