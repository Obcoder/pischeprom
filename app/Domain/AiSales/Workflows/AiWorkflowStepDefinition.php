<?php

namespace App\Domain\AiSales\Workflows;

use InvalidArgumentException;

final readonly class AiWorkflowStepDefinition
{
    public function __construct(
        public int $sequence,
        public string $toolCode,
        public string $toolVersion,
        public array $fixedInput,
        public string $stopCondition,
    ) {
        if ($sequence < 1 || preg_match('/^[a-z][a-z0-9_.]{2,95}$/', $toolCode) !== 1) {
            throw new InvalidArgumentException('Workflow step identity is invalid.');
        }

        if (! in_array($stopCondition, ['continue_on_success', 'stop_on_failure'], true)) {
            throw new InvalidArgumentException('Workflow stop condition is not code-owned.');
        }
    }

    public function safeMetadata(): array
    {
        return [
            'sequence' => $this->sequence,
            'tool_code' => $this->toolCode,
            'tool_version' => $this->toolVersion,
            'input_mapping' => 'code_owned',
            'stop_condition' => $this->stopCondition,
        ];
    }
}
