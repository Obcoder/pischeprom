<?php

namespace App\Domain\AiSales\Workflows;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentRun;
use LogicException;

class AiWorkflowRegistry
{
    /** @var array<string, AiWorkflowDefinition> */
    private array $definitions = [];

    /** @param null|list<AiWorkflowDefinition> $definitions */
    public function __construct(?array $definitions = null)
    {
        foreach ($definitions ?? [$this->syntheticWorkflow()] as $definition) {
            $this->register($definition);
        }
    }

    public function register(AiWorkflowDefinition $definition): void
    {
        $key = $this->key($definition->code, $definition->version);

        if (isset($this->definitions[$key])) {
            throw new LogicException("AI workflow {$key} is already registered.");
        }

        $this->definitions[$key] = $definition;
    }

    public function get(string $code, string $version): AiWorkflowDefinition
    {
        return $this->definitions[$this->key($code, $version)]
            ?? throw new PolicyViolation('unknown_workflow_blocked', 'Unknown workflows are blocked by the code-owned registry.');
    }

    public function resolveForRun(AiAgentRun $run): AiWorkflowDefinition
    {
        $matches = collect($this->definitions)->filter(function (AiWorkflowDefinition $workflow) use ($run): bool {
            return in_array($run->definition_code.':'.$run->definition_version, $workflow->allowedAgentDefinitions, true)
                && in_array($run->task_profile, $workflow->allowedTaskProfiles, true)
                && in_array($run->purpose, $workflow->allowedPurposes, true)
                && in_array($run->audience, $workflow->allowedAudiences, true)
                && in_array($run->lane, $workflow->allowedLanes, true)
                && in_array($run->role_code, $workflow->allowedRoles, true)
                && $run->selected_contour === $workflow->requiredContour;
        })->values();

        if ($matches->count() !== 1) {
            throw new PolicyViolation('workflow_selection_blocked', 'Exactly one server-owned workflow must match the run.');
        }

        return $matches->first();
    }

    /** @return list<AiWorkflowDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    private function syntheticWorkflow(): AiWorkflowDefinition
    {
        return new AiWorkflowDefinition(
            'synthetic.good_context_classification.v1',
            '1',
            'Development/test-only server-owned classification of a repository synthetic good.',
            ['unit_public_research_synthetic:1'],
            [AiTaskProfile::PublicCompanyResearch],
            [AiPurpose::UnitResearch],
            [AiAudience::Internal],
            BusinessLane::cases(),
            UnitRoleCode::cases(),
            AiProcessingContour::ExternalSanitized,
            ['chat_completions', 'strict_structured_outputs', 'store_false'],
            [
                new AiWorkflowStepDefinition(
                    1,
                    'catalog.get_synthetic_good',
                    '1',
                    ['sku' => 'SYN-001'],
                    'stop_on_failure',
                ),
            ],
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary'],
                'properties' => [
                    'summary' => ['type' => 'string', 'maxLength' => 1000],
                ],
            ],
            1,
            8_192,
            10_000,
            1_000,
            '0.0000',
            false,
            false,
            true,
            true,
            false,
        );
    }

    private function key(string $code, string $version): string
    {
        return $code.':'.$version;
    }
}
