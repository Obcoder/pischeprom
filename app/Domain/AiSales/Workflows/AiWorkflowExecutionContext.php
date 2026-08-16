<?php

namespace App\Domain\AiSales\Workflows;

use InvalidArgumentException;

final readonly class AiWorkflowExecutionContext
{
    public function __construct(
        public int $runId,
        public int $runStepId,
        public int $actorUserId,
        public int $expectedRunLockVersion,
        public string $callerIdempotencyKey,
    ) {
        if ($runId <= 0 || $runStepId <= 0 || $actorUserId <= 0 || $expectedRunLockVersion < 1) {
            throw new InvalidArgumentException('Workflow execution bindings are invalid.');
        }

        if ($callerIdempotencyKey === '' || strlen($callerIdempotencyKey) > 128) {
            throw new InvalidArgumentException('Workflow idempotency key must be bounded.');
        }
    }
}
