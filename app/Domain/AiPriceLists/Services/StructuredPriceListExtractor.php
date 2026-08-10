<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\VatMode;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Normalization\CurrencyNormalizer;
use App\Domain\AiPriceLists\Normalization\TextNormalizer;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use Illuminate\Support\Collection;
use Throwable;

class StructuredPriceListExtractor
{
    public function __construct(
        private readonly StructuredTextModelProviderInterface $provider,
        private readonly StructuredOutputValidator $validator,
        private readonly AiUsageRecorder $usage,
        private readonly TextNormalizer $text,
        private readonly CurrencyNormalizer $currencies,
    ) {}

    public function configured(): bool
    {
        return $this->provider->configured();
    }

    public function extract(PriceListImport $import): int
    {
        $schema = $this->validator->schema();
        $instructions = (string) file_get_contents(resource_path('ai/prompts/price-list-v1.txt'));
        $count = 0;

        $import->items()->orderBy('position')->chunk(
            max(1, min(100, (int) config('ai-price-lists.ai.max_rows_per_chunk', 20))),
            function (Collection $rows) use ($import, $schema, $instructions, &$count): void {
                $count += $this->extractRows($import, $rows, $schema, $instructions);
            }
        );

        return $count;
    }

    private function extractRows(PriceListImport $import, Collection $rows, array $schema, string $instructions): int
    {
        $data = $rows->map(fn (PriceListImportItem $item) => [
            'source_locator' => [
                'sheet' => $item->source_sheet,
                'page' => $item->source_page,
                'table' => $item->source_table,
                'row' => $item->source_row,
                'cells' => $item->source_range,
            ],
            'raw_text' => mb_substr((string) $item->raw_text, 0, 3000),
            'cells' => collect($item->raw_cells ?: [])->take(30)
                ->map(fn ($value) => is_scalar($value) ? mb_substr((string) $value, 0, 500) : null)
                ->all(),
        ])->values()->all();

        try {
            $this->usage->guardBudget();
            $response = $this->provider->generate(new StructuredModelRequest(
                instructions: $instructions,
                data: json_encode(['rows' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                schema: $schema,
                schemaName: 'price_list_v1',
                promptVersion: (string) config('ai-price-lists.ai.prompt_version'),
                schemaVersion: (string) config('ai-price-lists.ai.schema_version'),
                safetyIdentifier: hash('sha256', 'price-list-user:'.$import->uuid),
            ));
            $this->validator->validate($response->data);
            $matchedSources = $this->validateEvidence($rows, $response->data['items']);
        } catch (ExternalAiException $exception) {
            $this->usage->failure($import, 'yandex_ai_studio', 'structured_extraction', $exception, (string) config('ai-price-lists.ai.model'));

            if ($exception->errorCode === 'ai_output_truncated' && $rows->count() > 1) {
                $middle = (int) ceil($rows->count() / 2);

                return $this->extractRows($import, $rows->slice(0, $middle)->values(), $schema, $instructions)
                    + $this->extractRows($import, $rows->slice($middle)->values(), $schema, $instructions);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $safe = new ExternalAiException('AI вернул результат, не прошедший строгую проверку схемы.', false, 'ai_schema_invalid');
            $this->usage->failure($import, 'yandex_ai_studio', 'structured_extraction', $safe, (string) config('ai-price-lists.ai.model'));
            throw $safe;
        }

        $this->usage->structured($import, $response);
        $import->forceFill(['stage_heartbeat_at' => now()])->save();
        $document = $response->data['document'];

        $documentDefaults = array_filter([
            'currency' => $document['default_currency'],
            'vat_mode' => $document['default_vat_mode'],
            'vat_rate' => $document['default_vat_rate'],
            'valid_from' => $document['valid_from'],
            'valid_to' => $document['valid_to'],
        ], fn ($value) => $value !== null);
        $metadata = $import->document_metadata ?: [];
        $metadata['ai_supplier_name'] ??= $document['supplier_name'];
        $metadata['ai_supplier_inn'] ??= $document['supplier_inn'];
        $metadata['ai_warnings'] = array_values(array_unique([
            ...(array) ($metadata['ai_warnings'] ?? []),
            ...$response->data['warnings'],
        ]));

        $import->forceFill([
            'document_defaults' => array_merge($import->document_defaults ?: [], $documentDefaults),
            'model_id' => $response->model,
            'prompt_version' => config('ai-price-lists.ai.prompt_version'),
            'schema_version' => config('ai-price-lists.ai.schema_version'),
            'document_metadata' => $metadata,
        ])->save();

        foreach ($response->data['items'] as $index => $aiItem) {
            $this->storeItem($import, $matchedSources[$index], $aiItem);
        }

        return count($response->data['items']);
    }

    private function storeItem(PriceListImport $import, PriceListImportItem $source, array $aiItem): void
    {
        $locator = $aiItem['source_locator'];
        $defaults = $import->document_defaults ?: [];
        $source->forceFill([
            'price_list_import_id' => $import->id,
            'position' => $source->position,
            'source_sheet' => $locator['sheet'],
            'source_page' => $locator['page'],
            'source_table' => $locator['table'],
            'source_row' => $locator['row'],
            'source_range' => $locator['cells'],
            'raw_text' => $source->raw_text,
            'raw_cells' => $source->raw_cells,
            'raw_name' => $this->text->display($aiItem['name']),
            'normalized_name' => $this->text->search($aiItem['name']),
            'supplier_sku' => $aiItem['supplier_sku'],
            'manufacturer_sku' => $aiItem['manufacturer_sku'],
            'barcode' => $aiItem['barcode'],
            'manufacturer' => $aiItem['manufacturer'],
            'brand' => $aiItem['brand'],
            'country_of_origin' => $aiItem['country_of_origin'],
            'package_description' => $aiItem['package_description'],
            'units_per_package' => $aiItem['units_per_package'],
            'net_quantity' => $aiItem['net_quantity'],
            'net_quantity_unit' => $aiItem['net_quantity_unit'],
            'price_basis_quantity' => $aiItem['price_basis_quantity'],
            'price_basis_unit' => $aiItem['price_basis_unit'],
            'minimum_order_quantity' => $aiItem['minimum_order_quantity'],
            'price' => $aiItem['price'],
            'currency_code' => $this->currencies->normalize($aiItem['currency']) ?: ($defaults['currency'] ?? null),
            'vat_mode' => $aiItem['vat_mode'] ?: ($defaults['vat_mode'] ?? VatMode::Unknown->value),
            'vat_rate' => $aiItem['vat_rate'] ?: ($defaults['vat_rate'] ?? null),
            'availability' => $aiItem['availability'],
            'valid_from' => $aiItem['valid_from'] ?: ($defaults['valid_from'] ?? null),
            'valid_to' => $aiItem['valid_to'] ?: ($defaults['valid_to'] ?? null),
            'notes' => $aiItem['notes'],
            'field_evidence' => $aiItem['field_evidence'],
            'warnings' => $aiItem['warnings'],
            'row_fingerprint' => $source->row_fingerprint,
            'decision_status' => ItemDecisionStatus::Unreviewed,
            'match_class' => MatchClass::None,
            'review_reason' => null,
        ])->save();
    }

    /** @return array<int, PriceListImportItem> */
    private function validateEvidence($sourceRows, array $items): array
    {
        $matched = [];
        $usedIds = [];

        foreach ($items as $index => $item) {
            $locator = $item['source_locator'] ?? [];

            if (($locator['row'] ?? null) === null) {
                throw new ExternalAiException('AI не сохранил проверяемую ссылку на исходную строку.', false, 'ai_evidence_invalid');
            }

            $source = $sourceRows->first(function (PriceListImportItem $source) use ($locator): bool {
                return (int) $source->source_row === (int) $locator['row']
                    && $source->source_sheet === ($locator['sheet'] ?? null)
                    && $this->sameNullableInteger($source->source_page, $locator['page'] ?? null)
                    && $this->sameNullableInteger($source->source_table, $locator['table'] ?? null)
                    && $source->source_range === ($locator['cells'] ?? null);
            });

            if (! $source || isset($usedIds[$source->id])) {
                throw new ExternalAiException('AI вернул отсутствующую или повторяющуюся исходную строку.', false, 'ai_evidence_invalid');
            }

            $usedIds[$source->id] = true;
            $matched[$index] = $source;
        }

        return $matched;
    }

    private function sameNullableInteger(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return (int) $left === (int) $right;
    }
}
