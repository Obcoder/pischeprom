<?php

namespace App\Jobs\Banking;

use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Services\SberHealthService;
use App\Models\BankConnection;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CheckSberCredentialsExpiryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onConnection((string) config('banking.queue_connection', 'redis'));
        $this->onQueue((string) config('banking.queue', 'banking'));
    }

    public function uniqueId(): string
    {
        return 'sber-credentials-expiry';
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('banking.lock_store', 'redis'));
    }

    public function handle(SberHealthService $health): void
    {
        $configurationReasons = $health->expiringReasons();

        BankConnection::query()
            ->where('provider', 'sber')
            ->whereIn('status', ['active', 'error', 'reauthorization_required'])
            ->each(function (BankConnection $connection) use ($configurationReasons): void {
                $reasons = $configurationReasons;
                $refreshExpiry = $connection->refresh_token_expires_at;

                if ($refreshExpiry) {
                    $days = (int) round(
                        CarbonImmutable::now()
                            ->startOfDay()
                            ->diffInDays(CarbonImmutable::instance($refreshExpiry)->startOfDay(), false)
                    );

                    if (in_array($days, [30, 14, 7], true) || $days < 7) {
                        $reasons[] = "refresh_token_expires_in_{$days}_days";
                    }
                }

                if ($connection->status->value === 'reauthorization_required') {
                    $reasons[] = 'reauthorization_required';
                }

                foreach (array_unique($reasons) as $reason) {
                    BankConnectionRequiresAttention::dispatch($connection, $reason);
                }
            });
    }
}
