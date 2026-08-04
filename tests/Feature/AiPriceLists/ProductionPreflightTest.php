<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;
use Illuminate\Support\Facades\Storage;

class ProductionPreflightTest extends AiPriceListTestCase
{
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
