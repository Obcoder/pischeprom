<?php

namespace App\Console\Commands;

use App\Services\Mail\YandexMailboxService;
use Illuminate\Console\Command;

class SyncYandexMailboxCommand extends Command
{
    protected $signature = 'mail:yandex-sync {--limit=1000}';

    protected $description = 'Sync Yandex mailbox headers into local database';

    public function handle(YandexMailboxService $service): int
    {
        $result = $service->syncAll((int) $this->option('limit'));

        $this->info('Incoming: ' . $result['incoming']);
        $this->info('Outgoing: ' . $result['outgoing']);

        return self::SUCCESS;
    }
}
