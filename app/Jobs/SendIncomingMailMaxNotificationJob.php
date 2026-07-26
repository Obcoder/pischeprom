<?php

namespace App\Jobs;

use App\Models\MailMessageMaxDelivery;
use App\Services\Mail\IncomingMailMaxNotificationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendIncomingMailMaxNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 180, 600, 1800];
    }

    public function middleware(): array
    {
        $delivery = MailMessageMaxDelivery::query()->find($this->deliveryId);
        $target = $delivery
            ? "{$delivery->target_type}:{$delivery->target_id}"
            : "delivery:{$this->deliveryId}";

        return [
            (new WithoutOverlapping('max-mail:'.sha1($target)))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(IncomingMailMaxNotificationSender $sender): void
    {
        $delivery = MailMessageMaxDelivery::query()->find($this->deliveryId);

        if (! $delivery || $delivery->status === MailMessageMaxDelivery::STATUS_SENT) {
            return;
        }

        $sender->send($delivery);
    }

    public function failed(?Throwable $exception): void
    {
        MailMessageMaxDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('status', '!=', MailMessageMaxDelivery::STATUS_SENT)
            ->update([
                'status' => MailMessageMaxDelivery::STATUS_FAILED,
                'last_error' => $exception?->getMessage() ?: 'Не удалось отправить письмо в MAX.',
            ]);
    }
}
