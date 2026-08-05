<?php

namespace App\Console\Commands;

use App\Models\AvitoConnection;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Console\Command;

class SyncAvitoMessengerCommand extends Command
{
    protected $signature = 'avito:messages-sync
        {--connection= : ID OAuth-подключения; без параметра синхронизируются активные источники}
        {--full : Загрузить максимально доступную историю (до лимита Avito offset=1000)}';

    protected $description = 'Archive Avito chats, messages and media in the local database/storage';

    public function handle(AvitoMessengerService $messenger): int
    {
        $connectionOption = $this->option('connection');
        $connections = [];

        if ($connectionOption !== null) {
            $connections[] = AvitoConnection::query()->findOrFail((int) $connectionOption);
        } else {
            $accounts = AvitoMessengerAccount::query()
                ->where('sync_enabled', true)
                ->with('connection')
                ->get();

            if ($accounts->isEmpty()) {
                $connections[] = null;
            } else {
                foreach ($accounts as $account) {
                    if ($account->source_key === 'client_credentials') {
                        $connections[] = null;
                    } elseif ($account->connection?->is_active) {
                        $connections[] = $account->connection;
                    }
                }
            }
        }

        $connections = collect($connections)
            ->unique(fn (?AvitoConnection $connection) => $connection?->id ?: 'client_credentials')
            ->values();
        $failed = 0;

        foreach ($connections as $connection) {
            try {
                $run = $messenger->sync($connection, (bool) $this->option('full'));
                $label = $connection?->name ?: 'client credentials';
                $this->info("{$label}: {$run->chats_seen} чатов, {$run->messages_seen} сообщений, {$run->messages_created} новых.");
            } catch (\Throwable $exception) {
                $failed++;
                report($exception);
                $this->error(($connection?->name ?: 'client credentials').': '.$exception->getMessage());
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
