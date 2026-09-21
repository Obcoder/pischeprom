<?php

namespace App\Jobs\Avito;

use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Services\Avito\AvitoAutoReplyDispatcher;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class BootstrapAvitoChatHistoryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 10;

    public int $maxExceptions = 3;

    public int $timeout = 840;

    public int $uniqueFor = 1800;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $chatId,
        public readonly bool $resumeAutoReplies = true,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->chatId;
    }

    public function handle(AvitoMessengerService $messenger, AvitoAutoReplyDispatcher $dispatcher): void
    {
        $settings = AvitoAutoReplySetting::current();
        $chat = AvitoChat::find($this->chatId);
        if (! $chat || $settings->mode === 'off' || $settings->emergency_stopped_at) {
            return;
        }

        if ($chat->history_synced_at === null) {
            $lock = Cache::lock('avito:auto-reply:initial-history:'.$chat->id, 870);
            if (! $lock->get()) {
                $this->release(30);

                return;
            }

            try {
                $chat->refresh();
                if ($chat->history_synced_at === null) {
                    // Import history outside the 120-second reply worker;
                    // attachments have their own independent archive jobs.
                    $messenger->refreshChat($chat, archiveMedia: false);
                }
                $chat->refresh();
                if ($chat->history_synced_at === null) {
                    throw new RuntimeException('Avito chat history import did not complete.');
                }
            } finally {
                $lock->release();
            }
        }

        if (! $this->resumeAutoReplies) {
            return;
        }

        // Resume only the newest live question, never replay imported history.
        $message = $chat->messages()->where('direction', 'in')
            ->whereBetween('remote_created_at', [now()->subMinutes(15), now()->addMinutes(5)])
            ->orderByDesc('remote_created_at')->orderByDesc('id')->first();
        if (! $message || $message->autoReplyDecisions()->exists()) {
            return;
        }

        if (! $dispatcher->dispatch($message) && ! $message->autoReplyDecisions()->exists()) {
            // On another worker an empty import can finish before the original
            // reply job releases its unique lock. Retry dispatch, not the import.
            $this->release(5);
        }
    }
}
