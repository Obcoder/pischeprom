<?php

namespace App\Domain\AiPriceLists\Services;

use Carbon\CarbonImmutable;
use Opis\JsonSchema\Validator;
use RuntimeException;

class StructuredOutputValidator
{
    public function schema(): array
    {
        $path = resource_path('ai/schemas/price-list-v1.json');
        $schema = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($schema)) {
            throw new RuntimeException('JSON Schema прайс-листа недоступна.');
        }

        return $schema;
    }

    public function validate(array $data): void
    {
        $validator = new Validator;
        $result = $validator->validate(
            json_decode(json_encode($data, JSON_THROW_ON_ERROR)),
            json_decode(json_encode($this->schema(), JSON_THROW_ON_ERROR)),
        );

        if (! $result->isValid()) {
            throw new RuntimeException('Структурированный ответ AI не соответствует price-list-v1 schema.');
        }

        $allowedVat = ['included', 'excluded', 'unknown'];

        foreach ($data['items'] ?? [] as $index => $item) {
            if (! in_array($item['vat_mode'] ?? null, $allowedVat, true)) {
                throw new RuntimeException("AI item {$index} contains an invalid VAT mode.");
            }

            if (($item['price'] ?? null) !== null && ! preg_match('/^[0-9]+(?:\.[0-9]{1,6})?$/', $item['price'])) {
                throw new RuntimeException("AI item {$index} contains an invalid decimal price.");
            }

            if (($item['price'] ?? null) !== null && preg_match('/[1-9]/', $item['price']) !== 1) {
                throw new RuntimeException("AI item {$index} contains a non-positive price.");
            }

            if (trim((string) ($item['name'] ?? '')) === '') {
                throw new RuntimeException("AI item {$index} contains an empty product name.");
            }

            $this->validateDateRange($item['valid_from'] ?? null, $item['valid_to'] ?? null, "AI item {$index}");
        }

        $document = $data['document'] ?? [];
        $this->validateDateRange($document['valid_from'] ?? null, $document['valid_to'] ?? null, 'AI document');
    }

    private function validateDateRange(?string $from, ?string $to, string $context): void
    {
        foreach (['valid_from' => $from, 'valid_to' => $to] as $field => $value) {
            if ($value === null) {
                continue;
            }

            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            if (! $date || $date->format('Y-m-d') !== $value) {
                throw new RuntimeException("{$context} contains an invalid {$field} date.");
            }
        }

        if ($from !== null && $to !== null && $to < $from) {
            throw new RuntimeException("{$context} contains an inverted validity period.");
        }
    }
}
