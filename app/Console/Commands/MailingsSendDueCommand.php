<?php

namespace App\Console\Commands;

use App\Jobs\SendMailingCampaignJob;
use App\Models\MailingCampaign;
use App\Services\CommercialOffers\MailingCampaignService;
use Illuminate\Console\Command;

class MailingsSendDueCommand extends Command
{
    protected $signature = 'mailings:send-due {--sync : Send synchronously instead of dispatching jobs}';

    protected $description = 'Dispatch scheduled commercial offer campaigns that are due.';

    public function handle(MailingCampaignService $campaigns): int
    {
        $query = MailingCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at');

        $count = 0;
        foreach ($query->cursor() as $campaign) {
            if ($this->option('sync') || config('queue.default') === 'sync') {
                $campaigns->startSending($campaign);
            } else {
                SendMailingCampaignJob::dispatch($campaign->id);
                $campaign->update(['status' => 'sending', 'started_at' => now()]);
            }
            $count++;
        }

        $this->info("Due campaigns processed: {$count}");

        return self::SUCCESS;
    }
}
