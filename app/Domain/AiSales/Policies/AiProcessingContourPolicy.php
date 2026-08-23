<?php

namespace App\Domain\AiSales\Policies;

use App\Domain\AiSales\DTO\Providers\AiResidencyVerification;
use App\Domain\AiSales\DTO\Routing\AiProcessingRouteDecision;
use App\Domain\AiSales\DTO\SafeAiDto;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiContextSanitizer;
use App\Domain\AiSales\Services\AiProcessingContourResolver;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;

class AiProcessingContourPolicy
{
    public function __construct(
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiContextSanitizer $sanitizer,
        private readonly AiProcessingContourResolver $resolver,
        private readonly DeterministicAiPayloadScanner $dlp,
    ) {}

    public function decide(
        SafeAiDto $dto,
        AiDisclosureContext $context,
        AiTaskProfile $taskProfile,
        AiProcessingContour $requestedContour,
        ?AiResidencyVerification $residency = null,
    ): AiProcessingRouteDecision {
        $subject = $dto::class;
        $classifications = [];
        $visibilities = [];
        $redactions = 0;
        $hasLocalOnlyField = false;

        foreach ($dto->fields() as $field => $_value) {
            $rule = $this->registry->find($subject, (string) $field);

            if (! $rule) {
                return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                    ['unclassified' => 1], [], 'unclassified_field');
            }

            $classifications[$rule->classification->value] = ($classifications[$rule->classification->value] ?? 0) + 1;
            $visibilities[$rule->visibilityScope->value] = ($visibilities[$rule->visibilityScope->value] ?? 0) + 1;
            $redactions += $rule->redactionRule === 'mask' ? 1 : 0;
            $hasLocalOnlyField = $hasLocalOnlyField
                || ! $rule->externalExportable
                || in_array($rule->classification, [DataClassification::Internal, DataClassification::PersonalData, DataClassification::Secret], true);
        }

        ksort($classifications);
        ksort($visibilities);

        if (($classifications[DataClassification::Secret->value] ?? 0) > 0) {
            return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                $classifications, $visibilities, 'secret_blocked');
        }

        $selected = $this->resolver->resolve($taskProfile, $requestedContour);

        if ($selected === AiProcessingContour::None) {
            return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                $classifications, $visibilities, 'contour_override_blocked');
        }

        if ($selected === AiProcessingContour::ExternalSanitized && $hasLocalOnlyField) {
            return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                $classifications, $visibilities, 'local_only_data_external_blocked');
        }

        $disclosureContext = new AiDisclosureContext(
            $context->unitId,
            $context->unitBusinessContextId,
            $context->lane,
            $context->role,
            $context->audience,
            $context->purpose,
            $selected === AiProcessingContour::ExternalSanitized,
        );

        try {
            $sanitized = $this->sanitizer->sanitize($dto, $disclosureContext);
        } catch (PolicyViolation $violation) {
            return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                $classifications, $visibilities, $violation->errorCode);
        }

        $scan = $this->dlp->scan($sanitized, $selected);

        if ($scan->secretCount > 0) {
            return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                $classifications, $visibilities, 'dlp_secret_blocked');
        }

        if ($selected === AiProcessingContour::ExternalSanitized && $scan->personalDataCount > 0) {
            return $this->decision($context, AiProcessingDecision::Block, AiProcessingContour::None,
                $classifications, $visibilities, 'dlp_personal_data_external_blocked');
        }

        if ($selected === AiProcessingContour::LocalRu && ! $residency?->current()) {
            return $this->decision($context, AiProcessingDecision::RequireReview, AiProcessingContour::LocalRu,
                $classifications, $visibilities, 'residency_unverified', $redactions, true);
        }

        return $this->decision(
            $context,
            $redactions > 0 ? AiProcessingDecision::Redact : AiProcessingDecision::Allow,
            $selected,
            $classifications,
            $visibilities,
            $redactions > 0 ? 'allowed_with_code_owned_redaction' : 'allowed',
            $redactions,
        );
    }

    private function decision(
        AiDisclosureContext $context,
        AiProcessingDecision $decision,
        AiProcessingContour $contour,
        array $classifications,
        array $visibilities,
        string $reason,
        int $redactions = 0,
        bool $review = false,
    ): AiProcessingRouteDecision {
        return new AiProcessingRouteDecision(
            $decision,
            $context->purpose,
            $context->audience,
            $context->lane,
            $context->role,
            $context->unitId,
            $context->unitBusinessContextId,
            $classifications,
            $visibilities,
            $contour,
            $reason,
            $redactions,
            $review,
            (string) config('ai-sales.policy_versions.disclosure', 'unknown'),
            (string) config('ai-sales.policy_versions.classification_registry', 'unknown'),
            (string) config('ai-sales.policy_versions.processing_contour', 'unknown'),
        );
    }
}
