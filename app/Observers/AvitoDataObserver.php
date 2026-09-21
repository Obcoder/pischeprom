<?php

namespace App\Observers;

use App\Events\AvitoDataChanged;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyExample;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageAttachment;
use App\Models\AvitoMessengerAccount;
use App\Models\AvitoMessengerSyncRun;
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

        // Capture only local identifiers while the affected records still exist.
        $changes = $this->changes($model);

        $model->getConnection()->afterCommit(function () use ($topics, $changes): void {
            try {
                $connection = (string) config('realtime.queue_connection', 'database');
                // Reuse the dedicated Commerce worker, never do network I/O in
                // an incoming webhook or while a stop request holds a row lock.
                if (config("queue.connections.{$connection}.driver") !== 'database') {
                    AvitoDataChanged::warnUnavailable();

                    return;
                }

                Event::dispatch(new AvitoDataChanged($topics, $changes));
            } catch (Throwable) {
                AvitoDataChanged::warnUnavailable();
            }
        });
    }

    private function changes(Model $model): array
    {
        if ($model instanceof AvitoChat) {
            return ['chat_ids' => [(int) $model->id], 'overview' => true, 'chats' => true];
        }
        if ($model instanceof AvitoMessage) {
            return [
                'chat_ids' => [(int) $model->avito_chat_id],
                'message_ids' => [(int) $model->id],
                'overview' => true,
                'chats' => true,
            ];
        }
        if ($model instanceof AvitoMessageAttachment) {
            $chatId = $model->message()->value('avito_chat_id');

            return [
                'chat_ids' => $chatId ? [(int) $chatId] : [],
                'message_ids' => [(int) $model->avito_message_id],
                'overview' => true,
                'chats' => true,
            ];
        }
        if ($model instanceof AvitoMessengerAccount || $model instanceof AvitoMessengerSyncRun) {
            return ['overview' => true, 'chats' => false];
        }

        return [];
    }
}
