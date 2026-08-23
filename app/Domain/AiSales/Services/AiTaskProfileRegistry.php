<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Exceptions\PolicyViolation;

class AiTaskProfileRegistry
{
    public function contour(AiTaskProfile $profile): AiProcessingContour
    {
        $value = config("ai-sales.task_profiles.{$profile->value}.contour");

        return AiProcessingContour::tryFrom((string) $value)
            ?? throw new PolicyViolation('unknown_task_profile', 'Task profile contour is not code-owned.');
    }

    public function modelProfile(AiTaskProfile $profile): AiModelProfile
    {
        $value = config("ai-sales.task_profiles.{$profile->value}.model_profile");

        return AiModelProfile::tryFrom((string) $value)
            ?? throw new PolicyViolation('unknown_model_profile', 'Task model profile is not code-owned.');
    }

    public function requirements(AiTaskProfile $profile): AiRequestRequirements
    {
        $contour = $this->contour($profile);

        return new AiRequestRequirements(
            (array) config("ai-sales.task_profiles.{$profile->value}.required_capabilities", []),
            max(1, (int) config('ai-sales.limits.max_tokens', 4_000)),
            max(1, (int) config('ai-sales.limits.max_output_tokens', 1_000)),
            $contour === AiProcessingContour::ExternalSanitized,
        );
    }

    public function modelId(AiProcessingContour $contour, AiModelProfile $profile): string
    {
        $modelId = config("ai-sales.model_profiles.{$contour->value}.{$profile->value}");

        if (! is_string($modelId) || $modelId === '' || mb_strlen($modelId) > 191) {
            throw new PolicyViolation('model_mapping_missing', 'Logical model profile has no code-owned mapping for this contour.');
        }

        return $modelId;
    }
}
