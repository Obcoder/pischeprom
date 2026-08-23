<?php

namespace App\Domain\AiSales\Workflows;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Support\AiCanonicalJson;
use InvalidArgumentException;

final readonly class AiWorkflowDefinition
{
    public string $workflowHash;

    public string $responseSchemaHash;

    public function __construct(
        public string $code,
        public string $version,
        public string $description,
        public array $allowedAgentDefinitions,
        public array $allowedTaskProfiles,
        public array $allowedPurposes,
        public array $allowedAudiences,
        public array $allowedLanes,
        public array $allowedRoles,
        public AiProcessingContour $requiredContour,
        public array $requiredProviderCapabilities,
        public array $steps,
        public array $responseSchema,
        public int $maxRows,
        public int $maxBytes,
        public int $maxDurationMs,
        public int $maxTokens,
        public string $maxCostRub,
        public bool $humanReviewRequired,
        public bool $requiresProviderNativeTools,
        public bool $syntheticOnly,
        public bool $enabled,
        public bool $liveEligible,
        public string $policyVersion = 'stage07-v1',
    ) {
        if (preg_match('/^[a-z][a-z0-9_.]{2,95}$/', $code) !== 1 || $version === '') {
            throw new InvalidArgumentException('Workflow identity is invalid.');
        }

        if ($steps === [] || count($steps) > 8 || $allowedAgentDefinitions === []
            || $allowedTaskProfiles === [] || $allowedPurposes === [] || $allowedAudiences === []
            || $allowedLanes === [] || $allowedRoles === [] || $requiredProviderCapabilities === []
            || $maxRows < 0 || $maxRows > 100 || $maxBytes < 1 || $maxBytes > 131_072
            || $maxDurationMs < 1 || $maxDurationMs > 60_000 || $maxTokens < 1 || $maxTokens > 100_000
            || preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $maxCostRub) !== 1) {
            throw new InvalidArgumentException('Workflow plan and capabilities must be explicit and bounded.');
        }

        foreach ($steps as $index => $step) {
            if (! $step instanceof AiWorkflowStepDefinition || $step->sequence !== $index + 1) {
                throw new InvalidArgumentException('Workflow steps must be typed, ordered and gap-free.');
            }
        }

        foreach ($allowedTaskProfiles as $value) {
            if (! $value instanceof AiTaskProfile) {
                throw new InvalidArgumentException('Workflow task profiles must be typed.');
            }
        }
        foreach ($allowedPurposes as $value) {
            if (! $value instanceof AiPurpose) {
                throw new InvalidArgumentException('Workflow purposes must be typed.');
            }
        }
        foreach ($allowedAudiences as $value) {
            if (! $value instanceof AiAudience) {
                throw new InvalidArgumentException('Workflow audiences must be typed.');
            }
        }
        foreach ($allowedLanes as $value) {
            if (! $value instanceof BusinessLane) {
                throw new InvalidArgumentException('Workflow lanes must be typed.');
            }
        }
        foreach ($allowedRoles as $value) {
            if (! $value instanceof UnitRoleCode) {
                throw new InvalidArgumentException('Workflow roles must be typed.');
            }
        }

        $this->responseSchemaHash = AiCanonicalJson::hash($responseSchema);
        $this->workflowHash = AiCanonicalJson::hash([
            'code' => $code,
            'version' => $version,
            'agents' => array_values($allowedAgentDefinitions),
            'task_profiles' => array_map(static fn (AiTaskProfile $item) => $item->value, $allowedTaskProfiles),
            'purposes' => array_map(static fn (AiPurpose $item) => $item->value, $allowedPurposes),
            'audiences' => array_map(static fn (AiAudience $item) => $item->value, $allowedAudiences),
            'lanes' => array_map(static fn (BusinessLane $item) => $item->value, $allowedLanes),
            'roles' => array_map(static fn (UnitRoleCode $item) => $item->value, $allowedRoles),
            'contour' => $requiredContour->value,
            'capabilities' => array_values($requiredProviderCapabilities),
            'steps' => array_map(static fn (AiWorkflowStepDefinition $step) => [
                'sequence' => $step->sequence,
                'tool' => $step->toolCode,
                'version' => $step->toolVersion,
                'input' => $step->fixedInput,
                'stop' => $step->stopCondition,
            ], $steps),
            'response_schema' => $responseSchema,
            'limits' => [$maxRows, $maxBytes, $maxDurationMs, $maxTokens, $maxCostRub],
            'human_review' => $humanReviewRequired,
            'native_tools' => $requiresProviderNativeTools,
            'synthetic_only' => $syntheticOnly,
            'policy_version' => $policyVersion,
        ]);
    }

    public function allowsTool(string $code, string $version): bool
    {
        return collect($this->steps)->contains(
            static fn (AiWorkflowStepDefinition $step): bool => $step->toolCode === $code && $step->toolVersion === $version,
        );
    }

    public function safeMetadata(bool $globallyEnabled): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
            'description' => $this->description,
            'workflow_hash' => $this->workflowHash,
            'response_schema_hash' => $this->responseSchemaHash,
            'agent_definitions' => $this->allowedAgentDefinitions,
            'task_profiles' => array_map(static fn (AiTaskProfile $item) => $item->value, $this->allowedTaskProfiles),
            'purposes' => array_map(static fn (AiPurpose $item) => $item->value, $this->allowedPurposes),
            'audiences' => array_map(static fn (AiAudience $item) => $item->value, $this->allowedAudiences),
            'lanes' => array_map(static fn (BusinessLane $item) => $item->value, $this->allowedLanes),
            'required_contour' => $this->requiredContour->value,
            'required_capabilities' => $this->requiredProviderCapabilities,
            'ordered_steps' => array_map(static fn (AiWorkflowStepDefinition $step) => $step->safeMetadata(), $this->steps),
            'enabled' => $this->enabled && $globallyEnabled,
            'registered_enabled' => $this->enabled,
            'synthetic_only' => $this->syntheticOnly,
            'live_eligible' => $this->liveEligible && $globallyEnabled,
            'provider_native_tools' => $this->requiresProviderNativeTools,
            'human_review_required' => $this->humanReviewRequired,
            'limits' => [
                'rows' => $this->maxRows,
                'bytes' => $this->maxBytes,
                'duration_ms' => $this->maxDurationMs,
                'tokens' => $this->maxTokens,
                'cost_rub' => $this->maxCostRub,
            ],
        ];
    }
}
