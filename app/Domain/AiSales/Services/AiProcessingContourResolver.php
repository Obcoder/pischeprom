<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiTaskProfile;

class AiProcessingContourResolver
{
    public function __construct(private readonly AiTaskProfileRegistry $profiles) {}

    public function resolve(AiTaskProfile $profile, AiProcessingContour $requested): AiProcessingContour
    {
        $required = $this->profiles->contour($profile);

        if ($requested === AiProcessingContour::None || $requested !== $required) {
            return AiProcessingContour::None;
        }

        return $required;
    }
}
