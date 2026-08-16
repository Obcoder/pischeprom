<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Support\AiCanonicalJson;
use InvalidArgumentException;

final readonly class AiToolDefinition
{
    public string $schemaHash;

    public function __construct(
        public string $code,
        public string $version,
        public string $description,
        public array $inputSchema,
        public array $outputSchema,
        public array $outputDtoClasses,
        public array $requiredPermissions,
        public array $allowedPurposes,
        public array $allowedAudiences,
        public array $allowedLanes,
        public array $allowedRoles,
        public array $allowedContours,
        public DataClassification $maximumClassification,
        public array $allowedVisibilityScopes,
        public string $sideEffectClass,
        public string $idempotencySemantics,
        public int $maxRows,
        public int $maxStringCharacters,
        public int $maxBytes,
        public int $maxDurationMs,
        public int $maxQueries,
        public int $maxCallsPerRun,
        public string $maxCostRub,
        public string $handlerClass,
        public bool $enabled,
        public bool $syntheticOnly,
        public bool $liveEligible,
        public bool $humanReviewRequired,
        public string $policyVersion = 'stage07-v1',
    ) {
        if (preg_match('/^[a-z][a-z0-9_.]{2,95}$/', $code) !== 1
            || preg_match('/^[A-Za-z0-9._-]{1,32}$/', $version) !== 1) {
            throw new InvalidArgumentException('Tool definition identity is invalid.');
        }

        if (! in_array($sideEffectClass, ['read_only', 'proposal_only'], true)) {
            throw new InvalidArgumentException('Stage 07 tools may only be read-only or proposal-only.');
        }

        if ($maxRows < 0 || $maxRows > 100 || $maxBytes < 1 || $maxBytes > 131_072
            || $maxDurationMs < 1 || $maxDurationMs > 30_000 || $maxQueries < 0 || $maxQueries > 25
            || $maxCallsPerRun < 1 || $maxCallsPerRun > 20 || $maxStringCharacters < 1
            || preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $maxCostRub) !== 1) {
            throw new InvalidArgumentException('Tool definition bounds are unsafe.');
        }

        if (! is_a($handlerClass, AiToolHandlerInterface::class, true)) {
            throw new InvalidArgumentException('Tool handler must implement the code-owned interface.');
        }

        $this->assertEnums($allowedPurposes, AiPurpose::class);
        $this->assertEnums($allowedAudiences, AiAudience::class);
        $this->assertEnums($allowedLanes, BusinessLane::class);
        $this->assertEnums($allowedRoles, UnitRoleCode::class);
        $this->assertEnums($allowedContours, AiProcessingContour::class);
        $this->assertEnums($allowedVisibilityScopes, UnitVisibilityScope::class);

        $this->schemaHash = AiCanonicalJson::hash([
            'input' => $inputSchema,
            'output' => $outputSchema,
            'output_dtos' => array_values($outputDtoClasses),
        ]);
    }

    public function safeMetadata(bool $globallyEnabled): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
            'description' => $this->description,
            'schema_hash' => $this->schemaHash,
            'policy_version' => $this->policyVersion,
            'required_permissions' => $this->requiredPermissions,
            'allowed_purposes' => array_map(static fn (AiPurpose $item) => $item->value, $this->allowedPurposes),
            'allowed_audiences' => array_map(static fn (AiAudience $item) => $item->value, $this->allowedAudiences),
            'allowed_lanes' => array_map(static fn (BusinessLane $item) => $item->value, $this->allowedLanes),
            'allowed_roles' => array_map(static fn (UnitRoleCode $item) => $item->value, $this->allowedRoles),
            'allowed_contours' => array_map(static fn (AiProcessingContour $item) => $item->value, $this->allowedContours),
            'maximum_classification' => $this->maximumClassification->value,
            'visibility_scopes' => array_map(static fn (UnitVisibilityScope $item) => $item->value, $this->allowedVisibilityScopes),
            'side_effect_class' => $this->sideEffectClass,
            'enabled' => $this->enabled && $globallyEnabled,
            'registered_enabled' => $this->enabled,
            'synthetic_only' => $this->syntheticOnly,
            'live_eligible' => $this->liveEligible && $globallyEnabled,
            'human_review_required' => $this->humanReviewRequired,
            'limits' => [
                'rows' => $this->maxRows,
                'bytes' => $this->maxBytes,
                'duration_ms' => $this->maxDurationMs,
                'queries' => $this->maxQueries,
                'calls_per_run' => $this->maxCallsPerRun,
                'cost_rub' => $this->maxCostRub,
            ],
        ];
    }

    private function assertEnums(array $values, string $class): void
    {
        if ($values === []) {
            throw new InvalidArgumentException('Tool policy dimensions must be explicit and non-empty.');
        }

        foreach ($values as $value) {
            if (! $value instanceof $class) {
                throw new InvalidArgumentException('Tool policy dimensions must use typed enums.');
            }
        }
    }
}
