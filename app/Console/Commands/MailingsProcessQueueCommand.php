<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MailingsProcessQueueCommand extends Command
{
    protected $signature = 'mailings:process-queue {--once : Process one queued job}';

    protected $description = 'Process queued mailing jobs with the configured Laravel queue connection.';

    public function handle(): int
    {
        Artisan::call('queue:work', [
            '--queue' => 'default',
            '--once' => (bool) $this->option('once'),
            '--stop-when-empty' => ! (bool) $this->option('once'),
        ]);

        $this->output->write(Artisan::output());

        return self::SUCCESS;
    }
}
