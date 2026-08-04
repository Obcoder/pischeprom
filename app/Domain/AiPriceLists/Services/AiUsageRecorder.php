<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\DTO\OcrResponse;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Models\AiUsageRecord;
use App\Models\PriceListImport;

class AiUsageRecorder
{
    public function guardBudget(): void
    {
        $daily = (int) AiUsageRecord::query()->where('created_at', '>=', now()->startOfDay())->sum('total_tokens');
        $monthly = (int) AiUsageRecord::query()->where('created_at', '>=', now()->startOfMonth())->sum('total_tokens');

        if ($daily >= (int) config('ai-price-lists.ai.daily_token_limit') || $monthly >= (int) config('ai-price-lists.ai.monthly_token_limit')) {
            throw new ExternalAiException(
                'Лимит AI на период исчерпан; требуется решение администратора.',
                false,
                'ai_budget_exceeded',
            );
        }
    }

    public function guardOcrBudget(int $requestedPages = 1): void
    {
        $daily = (int) AiUsageRecord::query()
            ->where('operation', 'ocr')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('pages');
        $monthly = (int) AiUsageRecord::query()
            ->where('operation', 'ocr')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('pages');
        $dailyLimit = (int) config('ai-price-lists.ai.daily_ocr_page_limit');
        $monthlyLimit = (int) config('ai-price-lists.ai.monthly_ocr_page_limit');

        if ($daily + $requestedPages > $dailyLimit || $monthly + $requestedPages > $monthlyLimit) {
            throw new ExternalAiException(
                'Лимит OCR на период исчерпан; требуется решение администратора.',
                false,
                'ocr_budget_exceeded',
            );
        }
    }

    public function structured(PriceListImport $import, StructuredModelResponse $response, string $status = 'success'): AiUsageRecord
    {
        $rate = config('ai-price-lists.ai.estimated_cost_per_1000_tokens');

        return AiUsageRecord::query()->create([
            'price_list_import_id' => $import->id,
            'provider' => 'yandex_ai_studio',
            'operation' => 'structured_extraction',
            'model' => $response->model,
            'external_request_id' => $response->externalRequestId,
            'prompt_version' => config('ai-price-lists.ai.prompt_version'),
            'schema_version' => config('ai-price-lists.ai.schema_version'),
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->totalTokens,
            'latency_ms' => $response->latencyMs,
            'status' => $status,
            'estimated_cost' => is_numeric($rate) ? number_format($response->totalTokens / 1000 * (float) $rate, 6, '.', '') : null,
            'cost_currency' => config('ai-price-lists.ai.cost_currency'),
            'cost_is_estimate' => true,
        ]);
    }

    public function candidateRerank(PriceListImport $import, StructuredModelResponse $response): AiUsageRecord
    {
        $rate = config('ai-price-lists.ai.estimated_cost_per_1000_tokens');

        return AiUsageRecord::query()->create([
            'price_list_import_id' => $import->id,
            'provider' => 'yandex_ai_studio',
            'operation' => 'candidate_reranking',
            'model' => $response->model,
            'external_request_id' => $response->externalRequestId,
            'prompt_version' => 'price-list-candidate-rerank-v1',
            'schema_version' => 'price-list-candidate-rerank-v1',
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->totalTokens,
            'latency_ms' => $response->latencyMs,
            'status' => 'success',
            'estimated_cost' => is_numeric($rate) ? number_format($response->totalTokens / 1000 * (float) $rate, 6, '.', '') : null,
            'cost_currency' => config('ai-price-lists.ai.cost_currency'),
            'cost_is_estimate' => true,
        ]);
    }

    public function classification(PriceListImport $import, StructuredModelResponse $response): AiUsageRecord
    {
        $rate = config('ai-price-lists.ai.estimated_cost_per_1000_tokens');

        return AiUsageRecord::query()->create([
            'price_list_import_id' => $import->id,
            'provider' => 'yandex_ai_studio',
            'operation' => 'document_classification',
            'model' => $response->model,
            'external_request_id' => $response->externalRequestId,
            'prompt_version' => 'price-list-classification-v1',
            'schema_version' => 'price-list-classification-v1',
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->totalTokens,
            'latency_ms' => $response->latencyMs,
            'status' => 'success',
            'estimated_cost' => is_numeric($rate) ? number_format($response->totalTokens / 1000 * (float) $rate, 6, '.', '') : null,
            'cost_currency' => config('ai-price-lists.ai.cost_currency'),
            'cost_is_estimate' => true,
        ]);
    }

    public function ocr(PriceListImport $import, OcrResponse $response, string $status = 'success'): AiUsageRecord
    {
        return AiUsageRecord::query()->create([
            'price_list_import_id' => $import->id,
            'provider' => 'yandex_vision',
            'operation' => 'ocr',
            'model' => config('ai-price-lists.ocr.model'),
            'external_request_id' => $response->externalRequestId,
            'units' => $response->pages,
            'pages' => $response->pages,
            'latency_ms' => $response->latencyMs,
            'status' => $status,
            'cost_is_estimate' => true,
            'metadata' => $response->metadata,
        ]);
    }

    public function failure(
        PriceListImport $import,
        string $provider,
        string $operation,
        ExternalAiException $exception,
        ?string $model = null,
    ): AiUsageRecord {
        return AiUsageRecord::query()->create([
            'price_list_import_id' => $import->id,
            'provider' => $provider,
            'operation' => $operation,
            'model' => $model,
            'external_request_id' => $exception->externalRequestId,
            'prompt_version' => match ($operation) {
                'structured_extraction' => config('ai-price-lists.ai.prompt_version'),
                'candidate_reranking' => 'price-list-candidate-rerank-v1',
                'document_classification' => 'price-list-classification-v1',
                default => null,
            },
            'schema_version' => match ($operation) {
                'structured_extraction' => config('ai-price-lists.ai.schema_version'),
                'candidate_reranking' => 'price-list-candidate-rerank-v1',
                'document_classification' => 'price-list-classification-v1',
                default => null,
            },
            'attempt' => 1,
            'status' => 'failed',
            'error_code' => $exception->errorCode,
            'cost_is_estimate' => true,
            'metadata' => ['retryable' => $exception->retryable],
        ]);
    }
}
