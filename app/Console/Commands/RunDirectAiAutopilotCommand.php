<?php

namespace App\Console\Commands;

use App\Jobs\DirectAiAutopilotJob;
use App\Models\YandexAccount;
use App\Services\Yandex\AI\DirectAiAutopilotEngine;
use Illuminate\Console\Command;

class RunDirectAiAutopilotCommand extends Command
{
    protected $signature = 'yandex:direct:ai:autopilot {--account=} {--queue : Dispatch cycle to queue instead of running inline}';

    protected $description = 'Run Yandex Direct AI Autopilot decision cycle';

    public function handle(DirectAiAutopilotEngine $engine): int
    {
        $accountId = $this->option('account') ? (int) $this->option('account') : null;

        if ($accountId && !YandexAccount::query()->whereKey($accountId)->exists()) {
            $this->error("Yandex account {$accountId} not found.");

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            DirectAiAutopilotJob::dispatch($accountId);
            $this->info('Direct AI Autopilot job queued.');

            return self::SUCCESS;
        }

        $account = $accountId ? YandexAccount::query()->find($accountId) : null;
        $result = $engine->processCycle($account);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
