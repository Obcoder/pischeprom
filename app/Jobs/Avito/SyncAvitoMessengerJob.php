<?php

namespace App\Jobs\Avito;

use App\Models\AvitoConnection;
use App\Models\AvitoMessengerSyncRun;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAvitoMessengerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public readonly int $syncRunId,
        public readonly ?int $connectionId = null,
        public readonly bool $full = false,
    ) {}

    public function handle(AvitoMessengerService $messenger): void
    {
        $run = AvitoMessengerSyncRun::query()->findOrFail($this->syncRunId);
        $connection = $this->connectionId
            ? AvitoConnection::query()->findOrFail($this->connectionId)
            : null;

        $messenger->sync($connection, $this->full, $run);
    }
}
