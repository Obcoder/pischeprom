<?php

namespace App\Jobs\Avito;

use App\Models\AvitoMessage;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessAvitoAutoReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public array $backoff = [30, 60, 120];

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $messageId,
        public readonly bool $historical = false,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->messageId;
    }

    public function middleware(): array
    {
        $chatId = AvitoMessage::query()->whereKey($this->messageId)->value('avito_chat_id') ?: $this->messageId;

        return [
            (new WithoutOverlapping("avito-auto-reply-chat:{$chatId}"))
                ->releaseAfter(10)
                ->expireAfter(150),
        ];
    }

    public function handle(AvitoAutoReplyService $service): void
    {
        $decision = $service->evaluateWebhookMessage($this->messageId, $this->historical);

        if ($this->job && $this->attempts() < $this->tries
            && in_array($decision?->reason_code, ['classifier_error', 'send_lock_busy'], true)) {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
        }
    }
}
