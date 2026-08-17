<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\SafeAiDto;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use JsonException;

class PublicResearchSafeDtoPolicy
{
    public function __construct(
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiToolDlpGuard $dlp,
    ) {}

    public function sanitize(SafeAiDto $dto, ProspectingPurpose $purpose): array
    {
        $subject = $dto::class;
        $aiPurpose = AiPurpose::from($purpose->value);
        $audience = $purpose === ProspectingPurpose::BuyerDiscovery
            ? AiAudience::ProspectiveCustomer
            : AiAudience::ProspectiveSupplier;
        $fields = $dto->fields();
        foreach ($fields as $field => $value) {
            $rule = $this->registry->find($subject, (string) $field);
            if (! $rule) {
                throw new PolicyViolation('unclassified_field', 'Unclassified public research fields are blocked.');
            }
            if ($rule->classification !== DataClassification::Public
                || $rule->visibilityScope !== UnitVisibilityScope::SharedPublic
                || ! $rule->externalExportable
                || ! in_array($aiPurpose->value, $rule->allowedPurposes, true)
                || ! in_array($audience->value, $rule->allowedAudiences, true)) {
                throw new PolicyViolation('public_research_disclosure_blocked', 'Public research disclosure policy denied a field.');
            }
            $this->assertScalarTree($value);
        }
        try {
            $encoded = json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new PolicyViolation('invalid_safe_dto', 'Public research DTO could not be encoded.');
        }
        if (strlen($encoded) > $dto->maxBytes()) {
            throw new PolicyViolation('safe_dto_byte_limit', 'Public research DTO exceeds its byte limit.');
        }
        $this->dlp->assertPayloadSafe($fields, AiProcessingContour::ExternalSanitized, $purpose->lane());

        return $fields;
    }

    private function assertScalarTree(mixed $value): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }
        if (! is_array($value)) {
            throw new PolicyViolation('unsafe_dto_value', 'Public research DTO contains an unsafe value.');
        }
        foreach ($value as $item) {
            $this->assertScalarTree($item);
        }
    }
}
