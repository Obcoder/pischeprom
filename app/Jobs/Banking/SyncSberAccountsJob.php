<?php

namespace App\Jobs\Banking;

use App\Domain\Banking\Exceptions\BankingException;
use App\Domain\Banking\Services\BankSyncService;
use App\Models\BankConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncSberAccountsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $connectionId,
        public readonly bool $dispatchInitialImport = false,
    ) {
        $this->onConnection((string) config('banking.queue_connection', 'redis'));
        $this->onQueue((string) config('banking.queue', 'banking'));
    }

    public function uniqueId(): string
    {
        return "sber-accounts:{$this->connectionId}";
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('banking.lock_store', 'redis'));
    }

    public function backoff(): array
    {
        return [30 + random_int(0, 15), 120 + random_int(0, 30), 600 + random_int(0, 60)];
    }

    public function handle(BankSyncService $sync): void
    {
        $connection = BankConnection::query()->findOrFail($this->connectionId);

        try {
            $sync->syncAccounts($connection, $this->attempts());
        } catch (BankingException $exception) {
            if ($exception->retryAfterSeconds !== null) {
                $this->release($exception->retryAfterSeconds + random_int(0, 10));

                return;
            }

            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        if ($this->dispatchInitialImport) {
            SyncSberStatementsJob::dispatch($connection->id, 'initial');
        }
    }
}
