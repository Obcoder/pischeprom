<?php

namespace App\Jobs;

use App\Models\GoodStockAlert;
use App\Services\Goods\GoodStockAlertMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendGoodStockAlertConfirmationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $alertId,
        public string $token,
    ) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(GoodStockAlertMessenger $messenger): void
    {
        $alert = GoodStockAlert::query()
            ->with(['good', 'maxChat'])
            ->find($this->alertId);

        if (! $alert || $alert->status !== GoodStockAlert::STATUS_ACTIVE) {
            return;
        }

        $result = $messenger->sendConfirmation($alert, $this->token);

        if (! $result['ok']) {
            $alert->update([
                'error_message' => $result['error'] ?: 'MAX не принял подтверждение подписки.',
            ]);

            throw new RuntimeException(
                $result['error'] ?: 'MAX не принял подтверждение подписки.'
            );
        }

        $alert->update([
            'confirmation_sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        GoodStockAlert::query()
            ->whereKey($this->alertId)
            ->update([
                'error_message' => $exception?->getMessage() ?: 'Не удалось подтвердить подписку в MAX.',
            ]);
    }
}
