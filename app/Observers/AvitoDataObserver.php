<?php

namespace App\Observers;

use App\Events\AvitoDataChanged;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyExample;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Throwable;

class AvitoDataObserver
{
    public function saved(Model $model): void
    {
        // Rescanning an unchanged archive must not cause another browser refresh.
        // saved() still has unsynchronized originals; wasChanged() may describe
        // an earlier save of this same model instance.
        $changed = array_diff(array_keys($model->getDirty()), [
            'updated_at', 'last_synced_at', 'crm_scanned_at', 'payload',
        ]);
        if ($changed !== []) {
            $this->publishAfterCommit($model);
        }
    }

    public function deleted(Model $model): void
    {
        $this->publishAfterCommit($model);
    }

    private function publishAfterCommit(Model $model): void
    {
        if (! config('realtime.enabled')) {
            return;
        }

        $topics = match (true) {
            $model instanceof AvitoAutoReplySetting,
            $model instanceof AvitoAutoReplyRule,
            $model instanceof AvitoAutoReplyExample,
            $model instanceof AvitoAutoReplyDecision => ['avito_auto_replies'],
            default => ['avito_messages'],
        };

        $model->getConnection()->afterCommit(function () use ($topics): void {
            try {
                $connection = (string) config('realtime.queue_connection', 'database');
                // Reuse the dedicated Commerce worker, never do network I/O in
                // an incoming webhook or while a stop request holds a row lock.
                if (config("queue.connections.{$connection}.driver") !== 'database') {
                    AvitoDataChanged::warnUnavailable();

                    return;
                }

                Event::dispatch(new AvitoDataChanged($topics));
            } catch (Throwable) {
                AvitoDataChanged::warnUnavailable();
            }
        });
    }
}
