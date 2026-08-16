<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use InvalidArgumentException;

final readonly class AiToolExecutionContext
{
    public function __construct(
        public int $runId,
        public int $runStepId,
        public int $actorUserId,
        public int $unitId,
        public int $unitBusinessContextId,
        public BusinessLane $lane,
        public UnitRoleCode $role,
        public AiPurpose $purpose,
        public AiAudience $audience,
        public AiProcessingContour $contour,
        public string $workflowCode,
        public string $workflowVersion,
        public string $workflowHash,
        public int $policyDecisionId,
        public string $policyDecisionHash,
        public string $safeInputHash,
        public int $expectedRunLockVersion,
        public int $reservedRows,
        public int $reservedBytes,
        public int $reservedDurationMs,
        public string $reservedCostRub,
        public bool $syntheticOnly,
    ) {
        foreach ([$runId, $runStepId, $actorUserId, $unitId, $unitBusinessContextId, $policyDecisionId] as $id) {
            if ($id <= 0) {
                throw new InvalidArgumentException('Tool execution bindings require positive identifiers.');
            }
        }

        foreach ([$workflowHash, $policyDecisionHash, $safeInputHash] as $hash) {
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new InvalidArgumentException('Tool execution bindings require SHA-256 hashes.');
            }
        }

        if ($expectedRunLockVersion < 1 || $reservedRows < 0 || $reservedBytes < 1 || $reservedDurationMs < 1
            || preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $reservedCostRub) !== 1) {
            throw new InvalidArgumentException('Tool execution budget reservation is invalid.');
        }
    }

    public function budgetSnapshot(): array
    {
        return [
            'rows' => $this->reservedRows,
            'bytes' => $this->reservedBytes,
            'duration_ms' => $this->reservedDurationMs,
            'cost_rub' => $this->reservedCostRub,
        ];
    }
}
