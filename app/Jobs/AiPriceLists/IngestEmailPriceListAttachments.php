<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Services\EmailPriceListAttachmentCollector;
use App\Models\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class IngestEmailPriceListAttachments implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 300;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $mailMessageId)
    {
        $this->onConnection((string) config(
            'ai-price-lists.mail_ingestion.queue_connection',
            config('ai-price-lists.queue_connection', 'redis'),
        ));
        $this->onQueue((string) config('ai-price-lists.mail_ingestion.queue', 'mail-sync'));
    }

    public function uniqueId(): string
    {
        return (string) $this->mailMessageId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(EmailPriceListAttachmentCollector $collector): void
    {
        $message = MailMessage::query()->find($this->mailMessageId);

        if (! $message) {
            return;
        }

        $report = $collector->collect($message);

        logger()->info('AI price-list email attachments processed', [
            'mail_message_id' => $message->id,
            ...$report,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error('AI price-list email ingestion exhausted retries', [
            'mail_message_id' => $this->mailMessageId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
