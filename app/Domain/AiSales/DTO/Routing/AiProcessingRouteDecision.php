<?php

namespace App\Domain\AiSales\DTO\Routing;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;

final readonly class AiProcessingRouteDecision
{
    public string $decisionHash;

    public function __construct(
        public AiProcessingDecision $decision,
        public AiPurpose $purpose,
        public AiAudience $audience,
        public BusinessLane $lane,
        public UnitRoleCode $role,
        public int $unitId,
        public int $unitBusinessContextId,
        public array $classificationSummary,
        public array $visibilitySummary,
        public AiProcessingContour $selectedContour,
        public string $reasonCode,
        public int $redactionCount,
        public bool $requiresHumanReview,
        public string $disclosurePolicyVersion,
        public string $classificationRegistryVersion,
        public string $contourPolicyVersion,
    ) {
        $canonical = [
            'decision' => $decision->value,
            'purpose' => $purpose->value,
            'audience' => $audience->value,
            'lane' => $lane->value,
            'role' => $role->value,
            'unit_id' => $unitId,
            'unit_business_context_id' => $unitBusinessContextId,
            'classification_summary' => $classificationSummary,
            'visibility_summary' => $visibilitySummary,
            'selected_contour' => $selectedContour->value,
            'reason_code' => $reasonCode,
            'redaction_count' => $redactionCount,
            'requires_human_review' => $requiresHumanReview,
            'disclosure_policy_version' => $disclosurePolicyVersion,
            'classification_registry_version' => $classificationRegistryVersion,
            'contour_policy_version' => $contourPolicyVersion,
        ];

        $this->decisionHash = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function permitsProviderSelection(): bool
    {
        return in_array($this->decision, [AiProcessingDecision::Allow, AiProcessingDecision::Redact], true)
            && $this->selectedContour !== AiProcessingContour::None
            && ! $this->requiresHumanReview;
    }

    public function safeSnapshot(): array
    {
        return [
            'decision' => $this->decision->value,
            'purpose' => $this->purpose->value,
            'audience' => $this->audience->value,
            'lane' => $this->lane->value,
            'role_code' => $this->role->value,
            'unit_id' => $this->unitId,
            'unit_business_context_id' => $this->unitBusinessContextId,
            'classification_summary' => $this->classificationSummary,
            'visibility_summary' => $this->visibilitySummary,
            'selected_contour' => $this->selectedContour->value,
            'reason_code' => $this->reasonCode,
            'redaction_count' => $this->redactionCount,
            'requires_human_review' => $this->requiresHumanReview,
            'decision_hash' => $this->decisionHash,
        ];
    }
}
