<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\SafeAiDto;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use JsonException;

class AiContextSanitizer
{
    public function __construct(
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiFieldAuthorizationService $authorization,
    ) {}

    public function sanitize(SafeAiDto $dto, AiDisclosureContext $context): array
    {
        $subject = $dto::class;
        $fields = $dto->fields();
        $sanitized = [];

        foreach ($fields as $field => $value) {
            $this->authorization->authorize($subject, (string) $field, $context);
            $rule = $this->registry->find($subject, (string) $field);

            if (! $rule) {
                throw new PolicyViolation('unclassified_field', 'Unclassified fields are blocked.', $subject.'.'.$field);
            }

            $sanitized[$field] = $this->redact($value, $rule->redactionRule);
        }

        try {
            $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new PolicyViolation('invalid_safe_dto', 'Safe DTO could not be encoded.');
        }

        if (strlen($encoded) > $dto->maxBytes()) {
            throw new PolicyViolation('safe_dto_byte_limit', 'Safe DTO exceeds its byte limit.');
        }

        return $sanitized;
    }

    private function redact(mixed $value, string $rule): mixed
    {
        if ($rule !== 'mask') {
            return $this->assertSafeValue($value);
        }

        if (! is_string($value)) {
            return $this->assertSafeValue($value);
        }

        $length = mb_strlen($value);

        return $length <= 4
            ? str_repeat('*', $length)
            : mb_substr($value, 0, 2).str_repeat('*', min(12, $length - 4)).mb_substr($value, -2);
    }

    private function assertSafeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw new PolicyViolation('unsafe_dto_value', 'Safe DTO fields may not contain objects or resources.');
        }

        foreach ($value as $item) {
            $this->assertSafeValue($item);
        }

        return $value;
    }
}
