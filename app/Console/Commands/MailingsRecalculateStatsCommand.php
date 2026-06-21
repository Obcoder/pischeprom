<?php

namespace App\Console\Commands;

use App\Models\MailingCampaign;
use App\Services\CommercialOffers\MailingCampaignService;
use Illuminate\Console\Command;

class MailingsRecalculateStatsCommand extends Command
{
    protected $signature = 'mailings:recalculate-stats {campaignId?}';

    protected $description = 'Recalculate mailing campaign counters from recipients.';

    public function handle(MailingCampaignService $campaigns): int
    {
        $query = MailingCampaign::query()->when($this->argument('campaignId'), fn ($q, $id) => $q->whereKey($id));
        $count = 0;

        foreach ($query->cursor() as $campaign) {
            $campaigns->recalculateStats($campaign);
            $count++;
        }

        $this->info("Campaigns recalculated: {$count}");

        return self::SUCCESS;
    }
}
