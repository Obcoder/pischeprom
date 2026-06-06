<?php

namespace App\Console\Commands;

use App\Services\Telephony\BeelinePbxService;
use Illuminate\Console\Command;

class SubscribeBeelineEventsCommand extends Command
{
    protected $signature = 'beeline:subscribe
        {--url= : Override callback URL sent to Beeline}
        {--expires=3600 : Subscription lifetime in seconds}
        {--pattern= : Optional Beeline extension/pattern filter}
        {--dry-run : Print request payload without sending it}';

    protected $description = 'Create or renew Beeline Cloud PBX event subscription';

    public function handle(BeelinePbxService $service): int
    {
        try {
            $result = $service->subscribeEvents([
                'url' => $this->option('url'),
                'expires' => (int) $this->option('expires'),
                'pattern' => $this->option('pattern'),
            ], (bool) $this->option('dry-run'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
