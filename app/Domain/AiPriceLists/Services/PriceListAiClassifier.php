<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Models\PriceListImport;
use Opis\JsonSchema\Validator;
use Throwable;

class PriceListAiClassifier
{
    public function __construct(
        private readonly StructuredTextModelProviderInterface $provider,
        private readonly AiUsageRecorder $usage,
    ) {}

    public function configured(): bool
    {
        return $this->provider->configured();
    }

    public function classify(PriceListImport $import, ExtractionResult $extraction): DocumentClass
    {
        $schema = $this->schema();
        $instructions = (string) file_get_contents(resource_path('ai/prompts/price-list-classification-v1.txt'));
        $rows = collect($extraction->rows)
            ->take(40)
            ->map(fn ($row) => mb_substr($row->text, 0, 500))
            ->values()
            ->all();

        try {
            $this->usage->guardBudget();
            $response = $this->provider->generate(new StructuredModelRequest(
                instructions: $instructions,
                data: json_encode([
                    'extension' => $import->extension,
                    'rows' => $rows,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                schema: $schema,
                schemaName: 'price_list_classification_v1',
                promptVersion: 'price-list-classification-v1',
                schemaVersion: 'price-list-classification-v1',
                safetyIdentifier: hash('sha256', 'price-list-classification:'.$import->uuid),
            ));
            $result = (new Validator)->validate(
                json_decode(json_encode($response->data, JSON_THROW_ON_ERROR)),
                json_decode(json_encode($schema, JSON_THROW_ON_ERROR)),
            );

            if (! $result->isValid()) {
                throw new ExternalAiException('AI-классификация не прошла строгую проверку.', false, 'ai_classification_schema_invalid');
            }

            $this->usage->classification($import, $response);
            $confidence = (float) $response->data['confidence'];

            if ($confidence < (float) config('ai-price-lists.ai.classification_min_confidence', 0.90)) {
                return DocumentClass::Uncertain;
            }

            return DocumentClass::tryFrom((string) $response->data['class']) ?? DocumentClass::Uncertain;
        } catch (ExternalAiException $exception) {
            $this->usage->failure($import, 'yandex_ai_studio', 'document_classification', $exception, (string) config('ai-price-lists.ai.model'));
        } catch (Throwable) {
            $exception = new ExternalAiException('AI-классификация не прошла строгую проверку.', false, 'ai_classification_schema_invalid');
            $this->usage->failure($import, 'yandex_ai_studio', 'document_classification', $exception, (string) config('ai-price-lists.ai.model'));
        }

        return DocumentClass::Uncertain;
    }

    private function schema(): array
    {
        $schema = json_decode(
            (string) file_get_contents(resource_path('ai/schemas/price-list-classification-v1.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($schema)) {
            throw new \RuntimeException('JSON Schema классификации прайс-листа недоступна.');
        }

        return $schema;
    }
}
