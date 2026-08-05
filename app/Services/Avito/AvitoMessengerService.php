<?php

namespace App\Services\Avito;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoChat;
use App\Models\AvitoConnection;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerSyncRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AvitoMessengerService
{
    public function __construct(
        private readonly AvitoApiCatalog $catalog,
        private readonly AvitoApiExecutor $executor,
        private readonly AvitoMessengerArchive $archive,
        private readonly AvitoMessengerMediaArchive $mediaArchive,
    ) {}

    public function sync(
        ?AvitoConnection $connection = null,
        bool $full = false,
        ?AvitoMessengerSyncRun $run = null,
    ): AvitoMessengerSyncRun {
        $sourceKey = $connection ? "oauth:{$connection->id}" : 'client_credentials';
        $lock = Cache::lock('avito:messenger-sync:'.hash('sha256', $sourceKey), 1800);

        if (! $lock->get()) {
            throw new AvitoException('Синхронизация этого аккаунта Avito уже выполняется.', 'sync_running', 409, true);
        }

        $run ??= AvitoMessengerSyncRun::query()->create([
            'status' => 'queued',
            'full_sync' => $full,
        ]);
        $stats = [
            'chats_seen' => 0,
            'chats_created' => 0,
            'messages_seen' => 0,
            'messages_created' => 0,
            'attachments_archived' => 0,
        ];

        try {
            $account = $this->archive->resolveAccount($connection);
            // The first successful pass must capture all history that Avito still
            // exposes. Later scheduled passes stay compact and process recency.
            $effectiveFull = $full || $account->last_synced_at === null;
            $run->update([
                'avito_messenger_account_id' => $account->id,
                'status' => 'running',
                'full_sync' => $effectiveFull,
                'started_at' => now(),
                'error_message' => null,
            ]);
            $account->update([
                'sync_status' => 'running',
                'last_sync_started_at' => now(),
                'last_sync_error' => null,
            ]);

            $chatLimit = $effectiveFull
                ? (int) config('avito.messenger.full_chat_limit')
                : (int) config('avito.messenger.incremental_chat_limit');
            $chatPageSize = (int) config('avito.messenger.chat_page_size');

            for ($offset = 0; $offset < $chatLimit && $offset <= 1000; $offset += $chatPageSize) {
                $limit = min($chatPageSize, $chatLimit - $offset);
                $result = $this->execute('getChatsV2', [
                    'path' => ['user_id' => $account->external_user_id],
                    'query' => [
                        'chat_types' => ['u2i', 'u2u', 'a2u'],
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ], $connection);
                $chats = (array) Arr::get($result, 'data.chats', []);

                foreach ($chats as $chatPayload) {
                    if (! is_array($chatPayload) || blank(Arr::get($chatPayload, 'id'))) {
                        continue;
                    }

                    $stats['chats_seen']++;
                    $existing = AvitoChat::query()
                        ->where('avito_messenger_account_id', $account->id)
                        ->where('external_chat_id', (string) Arr::get($chatPayload, 'id'))
                        ->first();
                    $chat = $this->archive->storeChat($account, $chatPayload);
                    if (! $existing) {
                        $stats['chats_created']++;
                    }

                    $messageLimit = ($effectiveFull || ! $existing)
                        ? (int) config('avito.messenger.message_limit_per_chat')
                        : (int) config('avito.messenger.message_page_size');
                    $messageStats = $this->syncChatMessages($chat, $connection, $messageLimit);
                    foreach ($messageStats as $key => $value) {
                        $stats[$key] += $value;
                    }
                }

                if (count($chats) < $limit) {
                    break;
                }
            }

            $account->update([
                'sync_status' => 'success',
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ]);
            $run->update($stats + [
                'status' => 'success',
                'finished_at' => now(),
                'error_message' => null,
            ]);

            return $run->fresh('account');
        } catch (\Throwable $exception) {
            $message = Str::limit($exception->getMessage(), 2000);
            $run->update($stats + [
                'status' => 'error',
                'finished_at' => now(),
                'error_message' => $message,
            ]);

            if (isset($account)) {
                $account->update([
                    'sync_status' => 'error',
                    'last_sync_error' => $message,
                ]);
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function refreshChat(AvitoChat $chat, int $messageLimit = 100): AvitoChat
    {
        $chat->loadMissing('account.connection');
        $result = $this->execute('getChatByIdV2', [
            'path' => [
                'user_id' => $chat->account->external_user_id,
                'chat_id' => $chat->external_chat_id,
            ],
        ], $chat->account->connection);

        if (is_array($result['data'] ?? null)) {
            $chat = $this->archive->storeChat($chat->account, $result['data']);
        }

        $this->syncChatMessages($chat, $chat->account->connection, min(1100, max(1, $messageLimit)));

        return $chat->fresh(['account', 'messages.attachments']);
    }

    public function sendText(AvitoChat $chat, string $text): AvitoMessage
    {
        $chat->loadMissing('account.connection');
        $result = $this->execute('postSendMessage', [
            'path' => [
                'user_id' => $chat->account->external_user_id,
                'chat_id' => $chat->external_chat_id,
            ],
            'body' => ['type' => 'text', 'message' => ['text' => $text]],
            'content_type' => 'application/json',
        ], $chat->account->connection);
        $message = is_array($result['data'] ?? null)
            ? $this->archive->storeMessage($chat, $result['data'])
            : null;

        if (! $message) {
            throw new AvitoException('Avito принял отправку, но не вернул созданное сообщение.', 'message_response', 502, true);
        }

        return $message;
    }

    public function sendImage(AvitoChat $chat, UploadedFile $image): AvitoMessage
    {
        $chat->loadMissing('account.connection');
        $upload = $this->execute('uploadImages', [
            'path' => ['user_id' => $chat->account->external_user_id],
            'body' => [],
            'content_type' => 'multipart/form-data',
        ], $chat->account->connection, [$image]);
        $uploadData = (array) ($upload['data'] ?? []);
        $imageId = (string) (Arr::get($uploadData, 'image_id') ?: array_key_first($uploadData) ?: '');

        if ($imageId === '') {
            throw new AvitoException('Avito не вернул идентификатор загруженного изображения.', 'image_upload', 502, true);
        }

        $result = $this->execute('postSendImageMessage', [
            'path' => [
                'user_id' => $chat->account->external_user_id,
                'chat_id' => $chat->external_chat_id,
            ],
            'body' => ['image_id' => $imageId],
            'content_type' => 'application/json',
        ], $chat->account->connection);
        $message = is_array($result['data'] ?? null)
            ? $this->archive->storeMessage($chat, $result['data'])
            : null;

        if (! $message) {
            throw new AvitoException('Avito принял изображение, но не вернул созданное сообщение.', 'message_response', 502, true);
        }

        $this->mediaArchive->archiveMessage($message);

        return $message->fresh('attachments');
    }

    public function deleteMessage(AvitoMessage $message): AvitoMessage
    {
        $message->loadMissing('chat.account.connection');
        $this->execute('deleteMessage', [
            'path' => [
                'user_id' => $message->chat->account->external_user_id,
                'chat_id' => $message->chat->external_chat_id,
                'message_id' => $message->external_message_id,
            ],
        ], $message->chat->account->connection);
        $message->update([
            'remote_type' => 'deleted',
            'deleted_from_avito_at' => now(),
            'last_synced_at' => now(),
        ]);
        $message->chat->update([
            'last_message_type' => $message->chat->last_message_id === $message->external_message_id
                ? 'deleted'
                : $message->chat->last_message_type,
            'last_message_preview' => $message->chat->last_message_id === $message->external_message_id
                ? 'Сообщение удалено на Avito'
                : $message->chat->last_message_preview,
        ]);

        return $message->fresh('attachments');
    }

    public function markRead(AvitoChat $chat): void
    {
        $chat->loadMissing('account.connection');
        $this->execute('chatRead', [
            'path' => [
                'user_id' => $chat->account->external_user_id,
                'chat_id' => $chat->external_chat_id,
            ],
        ], $chat->account->connection);
        $this->archive->markChatRead($chat);
    }

    public function blacklistPeer(AvitoChat $chat, int $reasonId): void
    {
        $chat->loadMissing('account.connection');

        if (blank($chat->peer_user_id)) {
            throw new AvitoException('В архиве нет идентификатора собеседника. Сначала обновите чат.', 'peer_missing', 422);
        }

        $context = array_filter([
            'item_id' => is_numeric($chat->context_id) ? (int) $chat->context_id : null,
            'reason_id' => $reasonId,
        ], fn ($value) => $value !== null);
        $this->execute('postBlacklistV2', [
            'path' => ['user_id' => $chat->account->external_user_id],
            'body' => ['users' => [[
                'user_id' => is_numeric($chat->peer_user_id) ? (int) $chat->peer_user_id : $chat->peer_user_id,
                'context' => $context,
            ]]],
            'content_type' => 'application/json',
        ], $chat->account->connection);
    }

    public function subscriptions(?AvitoConnection $connection = null): array
    {
        return (array) Arr::get($this->execute('getSubscriptions', [], $connection), 'data.subscriptions', []);
    }

    public function subscribe(?AvitoConnection $connection = null): array
    {
        return $this->execute('postWebhookV3', [
            'body' => ['url' => route('api.avito.webhook')],
            'content_type' => 'application/json',
        ], $connection);
    }

    public function unsubscribe(?AvitoConnection $connection = null): array
    {
        return $this->execute('postWebhookUnsubscribe', [
            'body' => ['url' => route('api.avito.webhook')],
            'content_type' => 'application/json',
        ], $connection);
    }

    private function syncChatMessages(AvitoChat $chat, ?AvitoConnection $connection, int $maximum): array
    {
        $stats = ['messages_seen' => 0, 'messages_created' => 0, 'attachments_archived' => 0];
        $pageSize = (int) config('avito.messenger.message_page_size');

        for ($offset = 0; $offset < $maximum && $offset <= 1000; $offset += $pageSize) {
            $limit = min($pageSize, $maximum - $offset);
            $result = $this->execute('getMessagesV3', [
                'path' => [
                    'user_id' => $chat->account->external_user_id,
                    'chat_id' => $chat->external_chat_id,
                ],
                'query' => ['limit' => $limit, 'offset' => $offset],
            ], $connection);
            $messages = array_is_list((array) ($result['data'] ?? [])) ? (array) $result['data'] : [];

            foreach ($messages as $payload) {
                if (! is_array($payload) || blank(Arr::get($payload, 'id'))) {
                    continue;
                }

                $stats['messages_seen']++;
                $exists = AvitoMessage::query()
                    ->where('avito_chat_id', $chat->id)
                    ->where('external_message_id', (string) Arr::get($payload, 'id'))
                    ->exists();
                $message = $this->archive->storeMessage($chat, $payload);
                if (! $exists) {
                    $stats['messages_created']++;
                }
                if ($message) {
                    $stats['attachments_archived'] += $this->mediaArchive->archiveMessage($message);
                }
            }

            if (count($messages) < $limit) {
                break;
            }
        }

        $this->archive->recalculateUnread($chat);

        return $stats;
    }

    private function execute(
        string $operationId,
        array $input,
        ?AvitoConnection $connection = null,
        array $files = [],
    ): array {
        $capability = $this->catalog->findOperation('messenger', $operationId);
        if ($capability['access'] === 'mutation') {
            $input['confirmation'] = (string) config('avito.mutation_confirmation');
        }

        $result = $this->executor->execute($capability['id'], $input, $connection, $files);

        if (! $result['ok']) {
            $message = (string) (Arr::get($result, 'data.error.message')
                ?: Arr::get($result, 'data.message')
                ?: "Avito вернул HTTP {$result['status']} для {$operationId}.");
            throw new AvitoException(Str::limit(strip_tags($message), 1000), 'messenger_remote', 502, true);
        }

        return $result;
    }
}
