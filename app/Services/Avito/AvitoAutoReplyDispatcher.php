<?php

namespace App\Services\Avito;

use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AvitoAutoReplyDispatcher
{
    public function dispatch(AvitoMessage $message): bool
    {
        if ($message->direction !== 'in' || $message->autoReplyDecisions()->exists()) {
            return false;
        }

        $job = (new ProcessAvitoAutoReplyJob($message->id))
            ->delay(now()->addSeconds(AvitoAutoReplySetting::current()->debounce_seconds));
        $lock = new UniqueLock(Cache::store());

        if (! $lock->acquire($job)) {
            return false;
        }

        try {
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            // A failed queue push must remain retryable when Avito redelivers
            // the event or the next scheduled synchronization sees it.
            $lock->release($job);

            throw $exception;
        }

        return true;
    }

    public function dispatchLatestFreshIncoming(AvitoChat $chat): void
    {
        // Only run after the chat has been archived completely, so the worker
        // can see a later customer message or an operator's existing answer.
        $message = $chat->messages()
            ->where('direction', 'in')
            ->whereBetween('remote_created_at', [now()->subMinutes(15), now()->addMinutes(5)])
            ->orderByDesc('remote_created_at')
            ->orderByDesc('id')
            ->first();

        if ($message) {
            $this->dispatch($message);
        }
    }
}
