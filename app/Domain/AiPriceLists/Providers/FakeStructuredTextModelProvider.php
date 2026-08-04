<?php

namespace App\Domain\AiPriceLists\Providers;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;

class FakeStructuredTextModelProvider implements StructuredTextModelProviderInterface
{
    public function __construct(private array $response = []) {}

    public function configured(): bool
    {
        return true;
    }

    public function generate(StructuredModelRequest $request): StructuredModelResponse
    {
        return new StructuredModelResponse(
            data: $this->response ?: [
                'document' => [
                    'is_price_list' => true,
                    'supplier_name' => null,
                    'supplier_inn' => null,
                    'default_currency' => 'RUB',
                    'default_vat_mode' => 'unknown',
                    'default_vat_rate' => null,
                    'valid_from' => null,
                    'valid_to' => null,
                    'notes' => null,
                ],
                'column_mapping' => [],
                'items' => [],
                'warnings' => [],
            ],
            model: 'fake-price-list-model',
            externalRequestId: 'fake-ai-request',
            inputTokens: 0,
            outputTokens: 0,
            totalTokens: 0,
            latencyMs: 0,
        );
    }
}
