<?php

namespace App\Domain\AiPriceLists\Providers;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;

class YandexAiStudioProvider implements StructuredTextModelProviderInterface
{
    public function configured(): bool
    {
        return filled(config('ai-price-lists.ai.api_key'))
            && filled(config('ai-price-lists.ai.folder_id'))
            && filled(config('ai-price-lists.ai.model'));
    }

    public function generate(StructuredModelRequest $request): StructuredModelResponse
    {
        if (! $this->configured()) {
            throw new ExternalAiException('Yandex AI Studio не настроен.', false, 'ai_not_configured');
        }

        $started = hrtime(true);
        $clientRequestId = (string) Str::uuid();
        $model = $this->modelUri();
        $response = $this->client($clientRequestId)
            ->post('/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'developer', 'content' => $request->instructions],
                    ['role' => 'user', 'content' => "<untrusted_document_data>\n{$request->data}\n</untrusted_document_data>"],
                ],
                'temperature' => 0,
                'max_completion_tokens' => 12000,
                'stream' => false,
                'store' => false,
                'safety_identifier' => $request->safetyIdentifier,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $request->schemaName,
                        'strict' => true,
                        'schema' => $request->schema,
                    ],
                ],
            ]);

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $externalId = $response->header('x-request-id') ?: $response->json('id') ?: $clientRequestId;

        if ($response->failed()) {
            throw $this->exceptionFor($response, (string) $externalId);
        }

        $finishReason = $response->json('choices.0.finish_reason');

        if ($finishReason === 'length') {
            throw new ExternalAiException(
                'AI не завершил JSON из-за лимита длины ответа.',
                true,
                'ai_output_truncated',
                (string) $externalId,
                $this->responseMetadata($response, $latency, $finishReason),
            );
        }

        if ($finishReason === 'content_filter') {
            throw new ExternalAiException(
                'AI не вернул результат из-за фильтра содержимого.',
                false,
                'ai_content_filtered',
                (string) $externalId,
                $this->responseMetadata($response, $latency, $finishReason),
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new ExternalAiException(
                'AI не вернул структурированный результат.',
                true,
                'ai_empty_response',
                (string) $externalId,
                $this->responseMetadata($response, $latency, $finishReason),
            );
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ExternalAiException(
                'AI вернул некорректный JSON.',
                true,
                'ai_invalid_json',
                (string) $externalId,
                $this->responseMetadata($response, $latency, $finishReason),
            );
        }

        if (! is_array($data)) {
            throw new ExternalAiException(
                'AI вернул результат неверного типа.',
                true,
                'ai_invalid_shape',
                (string) $externalId,
                $this->responseMetadata($response, $latency, $finishReason),
            );
        }

        return new StructuredModelResponse(
            data: $data,
            model: (string) ($response->json('model') ?: $model),
            externalRequestId: (string) $externalId,
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            totalTokens: (int) $response->json('usage.total_tokens', 0),
            latencyMs: $latency,
        );
    }

    private function responseMetadata(Response $response, int $latency, mixed $finishReason): array
    {
        return [
            'finish_reason' => is_string($finishReason) ? $finishReason : null,
            'input_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
            'latency_ms' => $latency,
        ];
    }

    private function client(string $clientRequestId): PendingRequest
    {
        return Http::baseUrl((string) config('ai-price-lists.ai.base_url'))
            ->asJson()
            ->acceptJson()
            ->timeout((int) config('ai-price-lists.limits.timeout_seconds'))
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => 'Api-Key '.config('ai-price-lists.ai.api_key'),
                'OpenAI-Project' => (string) config('ai-price-lists.ai.folder_id'),
                'x-data-logging-enabled' => 'false',
                'x-client-request-id' => $clientRequestId,
            ])
            ->retry(
                (int) config('ai-price-lists.limits.max_attempts'),
                fn (int $attempt) => min(8000, 250 * (2 ** ($attempt - 1)) + random_int(0, 250)),
                static function (\Throwable $exception, PendingRequest $request): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if (! $exception instanceof RequestException || ! $exception->response) {
                        return false;
                    }

                    return $exception->response->status() === 429 || $exception->response->serverError();
                },
                throw: false,
            );
    }

    private function modelUri(): string
    {
        $model = trim((string) config('ai-price-lists.ai.model'));

        return str_starts_with($model, 'gpt://')
            ? $model
            : 'gpt://'.config('ai-price-lists.ai.folder_id').'/'.ltrim($model, '/');
    }

    private function exceptionFor(Response $response, string $requestId): ExternalAiException
    {
        $status = $response->status();
        $retryable = $status === 429 || $status >= 500;
        $code = match ($status) {
            401, 403 => 'ai_auth_error',
            413 => 'ai_payload_too_large',
            429 => 'ai_rate_limited',
            default => $status >= 500 ? 'ai_unavailable' : 'ai_request_rejected',
        };

        return new ExternalAiException(
            $retryable ? 'AI-сервис временно недоступен.' : 'AI-сервис отклонил запрос.',
            $retryable,
            $code,
            $requestId,
        );
    }
}
