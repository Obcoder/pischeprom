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

    public int $tries = 3;

    public int $timeout = 60;

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
                ->expireAfter(90),
        ];
    }

    public function handle(AvitoAutoReplyService $service): void
    {
        $service->evaluateWebhookMessage($this->messageId, $this->historical);
    }
}
