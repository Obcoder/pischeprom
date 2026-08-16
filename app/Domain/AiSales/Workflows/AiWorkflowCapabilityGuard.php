<?php

namespace App\Domain\AiSales\Workflows;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Exceptions\PolicyViolation;

class AiWorkflowCapabilityGuard
{
    public function assertCompatible(
        AiWorkflowDefinition $workflow,
        string $providerCode,
        string $modelId,
    ): void {
        if ($workflow->requiresProviderNativeTools
            || in_array('function_calling', $workflow->requiredProviderCapabilities, true)
            || in_array('native_tool_calling', $workflow->requiredProviderCapabilities, true)
            || (bool) config('ai-sales.provider_native_tools_enabled', false)) {
            throw new PolicyViolation('workflow_native_tools_unsupported', 'Provider-native dynamic tool calling is disabled and unsupported.');
        }

        if ($providerCode !== 'timeweb') {
            return;
        }

        if ($workflow->requiredContour === AiProcessingContour::LocalRu) {
            $allowed = ['chat_completions', 'usage_reporting'];

            if ($modelId !== 'timeweb/gpt-oss-120b'
                || array_diff($workflow->requiredProviderCapabilities, $allowed) !== []) {
                throw new PolicyViolation(
                    'timeweb_local_capability_unverified',
                    'The local Timeweb model is limited to evidenced basic chat and usage normalization.',
                );
            }

            return;
        }

        if ($modelId === 'openai/gpt-5.6-luna'
            && array_intersect($workflow->requiredProviderCapabilities, ['function_calling', 'native_tool_calling']) !== []) {
            throw new PolicyViolation(
                'timeweb_luna_native_tools_unsupported',
                'The evidenced Luna profile does not support native provider tools.',
            );
        }
    }
}
