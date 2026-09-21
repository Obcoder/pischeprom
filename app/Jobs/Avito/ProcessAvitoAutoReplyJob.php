<?php

namespace App\Jobs\Avito;

use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoMessage;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use App\Services\Avito\AvitoAutoReplyDispatcher;
use App\Services\Avito\AvitoMessengerService;
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
        // The first webhook can create a chat before its history is imported.
        // Bootstrap that archive once, before AI can mistake it for a new dialog.
        $settings = AvitoAutoReplySetting::current();
        $message = AvitoMessage::with('chat')->find($this->messageId);
        $occurredAt = $message?->remote_created_at ?: $message?->created_at;
        if (! $this->historical && $message?->chat && $message->chat->history_synced_at === null
            && $message->direction === 'in' && $message->type === 'text' && $message->remote_type === 'text'
            && $occurredAt?->betweenIncluded(now()->subMinutes(15), now()->addMinutes(5))
            && $settings->mode !== 'off' && ! $settings->emergency_stopped_at) {
            $connection = $this->connection ?: config('queue.default');
            if (config('queue.connections.'.$connection.'.driver') !== 'sync') {
                BootstrapAvitoChatHistoryJob::dispatch($message->avito_chat_id)
                    ->onConnection($connection)->onQueue($this->queue);

                return;
            }

            // Keep the development-only synchronous driver finite: importing
            // inline must not recursively dispatch the currently running job.
            (new BootstrapAvitoChatHistoryJob($message->avito_chat_id, false))->handle(
                app(AvitoMessengerService::class), app(AvitoAutoReplyDispatcher::class),
            );
            if ($message->chat->fresh()?->history_synced_at === null) {
                return;
            }
        }
        $decision = $service->evaluateWebhookMessage($this->messageId, $this->historical);

        if ($this->job && $this->attempts() < $this->tries
            && in_array($decision?->reason_code, ['classifier_error', 'send_lock_busy'], true)) {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
        }
    }
}
