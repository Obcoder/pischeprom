<?php

namespace App\Domain\AiSales\Campaigns\Contracts;

use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignStageOutcome;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\User;

interface ClientAcquisitionCampaignStageProcessorInterface
{
    public function process(
        ClientAcquisitionCampaign $campaign,
        AiAgentRun $run,
        AiAgentRunStep $step,
        User $actor,
    ): ClientAcquisitionCampaignStageOutcome;
}
