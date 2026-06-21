<?php

namespace App\Console\Commands;

use App\Models\MailingWebhookCall;
use Illuminate\Console\Command;

class MailingsCleanupOldWebhookCallsCommand extends Command
{
    protected $signature = 'mailings:cleanup-old-webhook-calls {--days=30}';

    protected $description = 'Delete old raw Unisender webhook call bodies.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $count = MailingWebhookCall::query()->where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Webhook calls deleted: {$count}");

        return self::SUCCESS;
    }
}
