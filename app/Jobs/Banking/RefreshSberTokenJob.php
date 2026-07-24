<?php

namespace App\Jobs\Banking;

use App\Domain\Banking\Exceptions\BankingException;
use App\Domain\Banking\Providers\Sber\SberTokenManager;
use App\Models\BankConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RefreshSberTokenJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $connectionId)
    {
        $this->onConnection((string) config('banking.queue_connection', 'redis'));
        $this->onQueue((string) config('banking.queue', 'banking'));
    }

    public function uniqueId(): string
    {
        return "sber-token:{$this->connectionId}";
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('banking.lock_store', 'redis'));
    }

    public function backoff(): array
    {
        return [60 + random_int(0, 20), 300, 900];
    }

    public function handle(SberTokenManager $tokens): void
    {
        $connection = BankConnection::query()->findOrFail($this->connectionId);

        try {
            $tokens->refreshTokens($connection);
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
    }
}
