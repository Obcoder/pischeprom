<?php

namespace App\Jobs;

use App\Models\GoodStockAlert;
use App\Services\Goods\GoodStockAlertMessenger;
use App\Services\Goods\GoodStockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SendGoodStockAlertNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $alertId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        GoodStockAlertMessenger $messenger,
        GoodStockService $stock,
    ): void {
        $lock = Cache::lock("good-stock-alert:{$this->alertId}", 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $alert = GoodStockAlert::query()
                ->with(['good.seo', 'maxChat'])
                ->find($this->alertId);

            if (! $alert || $alert->status !== GoodStockAlert::STATUS_ACTIVE) {
                return;
            }

            if (! $stock->isInStock($alert->good_id)) {
                return;
            }

            $alert->update([
                'attempts' => $alert->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            $result = $messenger->sendAvailable($alert);

            if (! $result['ok']) {
                $alert->update([
                    'error_message' => $result['error'] ?: 'MAX не принял оповещение.',
                ]);

                throw new RuntimeException(
                    $result['error'] ?: 'MAX не принял оповещение.'
                );
            }

            $alert->update([
                'status' => GoodStockAlert::STATUS_NOTIFIED,
                'notified_at' => now(),
                'provider_message_id' => $this->messageId($result['data'] ?: []),
                'error_message' => null,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        GoodStockAlert::query()
            ->whereKey($this->alertId)
            ->where('status', GoodStockAlert::STATUS_ACTIVE)
            ->update([
                'status' => GoodStockAlert::STATUS_FAILED,
                'error_message' => $exception?->getMessage() ?: 'Не удалось отправить оповещение в MAX.',
            ]);
    }

    private function messageId(array $payload): ?string
    {
        foreach ([
            'message_id',
            'message.id',
            'message.mid',
            'message.body.mid',
            'body.mid',
            'mid',
            'id',
        ] as $path) {
            $value = data_get($payload, $path);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
