<?php

namespace App\Services\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoConnection;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageAttachment;
use App\Models\AvitoMessengerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AvitoMessengerArchive
{
    public function __construct(private readonly AvitoApiExecutor $executor) {}

    public function resolveAccount(?AvitoConnection $connection = null, bool $refreshIdentity = false): AvitoMessengerAccount
    {
        $sourceKey = $connection ? "oauth:{$connection->id}" : 'client_credentials';
        $account = AvitoMessengerAccount::query()->firstOrNew(['source_key' => $sourceKey]);
        $account->avito_connection_id = $connection?->id;

        $knownUserId = trim((string) ($connection?->external_user_id ?: $account->external_user_id));

        if ($knownUserId === '' || $refreshIdentity) {
            $capability = app(\App\Domain\Avito\Catalog\AvitoApiCatalog::class)
                ->findOperation('user', 'getUserInfoSelf');
            $result = $this->executor->execute($capability['id'], [], $connection);

            if (! $result['ok'] || ! is_array($result['data'] ?? null) || blank(Arr::get($result, 'data.id'))) {
                throw new \App\Domain\Avito\Exceptions\AvitoException(
                    'Avito не вернул идентификатор аккаунта для синхронизации сообщений.',
                    'messenger_account',
                    502,
                    true
                );
            }

            $knownUserId = (string) Arr::get($result, 'data.id');
            $account->name = (string) (Arr::get($result, 'data.name')
                ?: Arr::get($result, 'data.email')
                ?: $connection?->name
                ?: "Avito {$knownUserId}");

            if ($connection && $connection->external_user_id !== $knownUserId) {
                $connection->update(['external_user_id' => $knownUserId]);
            }
        }

        $account->external_user_id = $knownUserId;
        $account->name = $account->name ?: $connection?->name ?: "Avito {$knownUserId}";
        $account->sync_enabled = true;
        $account->save();

        return $account->fresh();
    }

    public function accountForWebhook(string $externalUserId): AvitoMessengerAccount
    {
        $account = AvitoMessengerAccount::query()
            ->where('external_user_id', $externalUserId)
            ->orderByRaw("CASE WHEN source_key = 'client_credentials' THEN 0 ELSE 1 END")
            ->first();

        if ($account) {
            return $account;
        }

        $connection = AvitoConnection::query()
            ->where('external_user_id', $externalUserId)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        return AvitoMessengerAccount::query()->create([
            'avito_connection_id' => $connection?->id,
            'source_key' => $connection ? "oauth:{$connection->id}" : 'client_credentials',
            'external_user_id' => $externalUserId,
            'name' => $connection?->name ?: "Avito {$externalUserId}",
            'sync_enabled' => true,
        ]);
    }

    public function storeChat(AvitoMessengerAccount $account, array $payload): AvitoChat
    {
        $externalChatId = trim((string) Arr::get($payload, 'id'));

        if ($externalChatId === '') {
            throw new \InvalidArgumentException('Avito chat id is missing.');
        }

        $context = (array) Arr::get($payload, 'context', []);
        $contextValue = (array) Arr::get($context, 'value', []);
        $lastMessage = (array) Arr::get($payload, 'last_message', []);
        $peer = $this->peerFromUsers((array) Arr::get($payload, 'users', []), (string) $account->external_user_id);
        $chat = AvitoChat::query()->firstOrNew([
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => $externalChatId,
        ]);

        $chat->fill([
            'chat_type' => Arr::get($payload, 'chat_type', $chat->chat_type),
            'context_type' => Arr::get($context, 'type', $chat->context_type),
            'context_id' => $this->nullableString(Arr::get($contextValue, 'id', $chat->context_id)),
            'title' => (string) (Arr::get($contextValue, 'title') ?: $chat->title ?: $peer['name'] ?: 'Чат Avito'),
            'context_url' => Arr::get($contextValue, 'url', $chat->context_url),
            'peer_user_id' => $peer['id'] ?: $chat->peer_user_id,
            'peer_name' => $peer['name'] ?: $chat->peer_name,
            'peer_avatar_url' => $peer['avatar'] ?: $chat->peer_avatar_url,
            'last_message_id' => $this->nullableString(Arr::get($lastMessage, 'id', $chat->last_message_id)),
            'last_message_type' => Arr::get($lastMessage, 'type', $chat->last_message_type),
            'last_message_preview' => $lastMessage !== []
                ? $this->preview($lastMessage)
                : $chat->last_message_preview,
            'is_unread' => $lastMessage !== []
                ? Arr::get($lastMessage, 'direction') === 'in' && ! (bool) Arr::get($lastMessage, 'is_read', false)
                : $chat->is_unread,
            'remote_created_at' => $this->timestamp(Arr::get($payload, 'created')) ?: $chat->remote_created_at,
            'remote_updated_at' => $this->timestamp(Arr::get($payload, 'updated')) ?: $chat->remote_updated_at,
            'last_message_at' => $this->timestamp(Arr::get($lastMessage, 'created')) ?: $chat->last_message_at,
            'last_synced_at' => now(),
            'payload' => $payload,
        ]);
        $chat->save();

        if ($lastMessage !== [] && filled(Arr::get($lastMessage, 'id'))) {
            $this->storeMessage($chat, $lastMessage);
        }

        return $chat->fresh();
    }

    public function storeMessage(AvitoChat $chat, array $payload): ?AvitoMessage
    {
        $externalMessageId = trim((string) Arr::get($payload, 'id'));

        if ($externalMessageId === '') {
            return null;
        }

        $remoteType = (string) (Arr::get($payload, 'type') ?: 'unknown');
        $content = (array) Arr::get($payload, 'content', []);
        $message = AvitoMessage::query()->firstOrNew([
            'avito_chat_id' => $chat->id,
            'external_message_id' => $externalMessageId,
        ]);
        $isNew = ! $message->exists;
        $direction = (string) Arr::get($payload, 'direction', '');

        if ($direction === '') {
            $direction = (string) Arr::get($payload, 'author_id') === (string) $chat->account->external_user_id
                ? 'out'
                : 'in';
        }

        $attributes = [
            'author_id' => $this->nullableString(Arr::get($payload, 'author_id')),
            'direction' => $direction,
            'remote_type' => $remoteType,
            'is_read' => (bool) Arr::get($payload, 'is_read', Arr::get($payload, 'read') !== null),
            'remote_created_at' => $this->timestamp(Arr::get($payload, 'created'))
                ?: $this->dateTime(Arr::get($payload, 'published_at'))
                ?: $message->remote_created_at,
            'remote_read_at' => $this->timestamp(Arr::get($payload, 'read')) ?: $message->remote_read_at,
            'deleted_from_avito_at' => $remoteType === 'deleted'
                ? ($message->deleted_from_avito_at ?: now())
                : null,
            'last_synced_at' => now(),
            'payload' => $payload,
        ];

        // A deleted message is retained as a tombstone by Avito. Keep the last
        // known original type and content in our archive instead of erasing it.
        if ($isNew || $remoteType !== 'deleted') {
            $attributes += [
                'type' => $remoteType,
                'text' => $this->nullableString(Arr::get($content, 'text')),
                'content' => $content,
                'quote' => Arr::get($payload, 'quote'),
            ];
        }

        $message->fill($attributes);
        $message->save();

        $this->storeAttachmentReferences($message, $content);
        $this->updateChatFromMessage($chat, $message);

        return $message->fresh('attachments');
    }

    public function ingestWebhook(array $payload): ?AvitoMessage
    {
        $value = (array) Arr::get($payload, 'payload.value', []);
        $chatId = trim((string) Arr::get($value, 'chat_id'));
        $userId = trim((string) Arr::get($value, 'user_id'));

        if ($chatId === '' || $userId === '' || blank(Arr::get($value, 'id'))) {
            return null;
        }

        $account = $this->accountForWebhook($userId);
        $chat = AvitoChat::query()->firstOrCreate(
            [
                'avito_messenger_account_id' => $account->id,
                'external_chat_id' => $chatId,
            ],
            [
                'chat_type' => Arr::get($value, 'chat_type'),
                'context_type' => filled(Arr::get($value, 'item_id')) ? 'item' : null,
                'context_id' => $this->nullableString(Arr::get($value, 'item_id')),
                'title' => filled(Arr::get($value, 'item_id')) ? 'Объявление '.Arr::get($value, 'item_id') : 'Чат Avito',
                'remote_created_at' => $this->timestamp(Arr::get($value, 'created')),
            ]
        );

        if (blank($chat->chat_type) && filled(Arr::get($value, 'chat_type'))) {
            $chat->update(['chat_type' => Arr::get($value, 'chat_type')]);
        }

        return $this->storeMessage($chat, $value);
    }

    public function markChatRead(AvitoChat $chat): void
    {
        $chat->messages()->where('direction', 'in')->update(['is_read' => true]);
        $chat->update(['is_unread' => false, 'unread_count' => 0]);
    }

    public function recalculateUnread(AvitoChat $chat): void
    {
        $count = $chat->messages()
            ->where('direction', 'in')
            ->where('is_read', false)
            ->where('remote_type', '!=', 'deleted')
            ->count();

        $chat->update(['is_unread' => $count > 0, 'unread_count' => $count]);
    }

    private function updateChatFromMessage(AvitoChat $chat, AvitoMessage $message): void
    {
        $isNewest = ! $chat->last_message_at
            || ! $message->remote_created_at
            || $message->remote_created_at->gte($chat->last_message_at);

        if ($isNewest) {
            $chat->fill([
                'last_message_id' => $message->external_message_id,
                'last_message_type' => $message->remote_type,
                'last_message_preview' => $message->remote_type === 'deleted'
                    ? 'Сообщение удалено на Avito'
                    : $this->preview([
                        'type' => $message->type,
                        'content' => $message->content ?: [],
                    ]),
                'last_message_at' => $message->remote_created_at ?: $chat->last_message_at,
                'remote_updated_at' => $message->remote_created_at ?: now(),
                'last_synced_at' => now(),
            ]);
        }

        if ($message->direction === 'in' && ! $message->is_read && $message->remote_type !== 'deleted') {
            $chat->is_unread = true;
        }

        $chat->save();
    }

    private function storeAttachmentReferences(AvitoMessage $message, array $content): void
    {
        $imageSizes = (array) Arr::get($content, 'image.sizes', []);
        if ($imageSizes !== []) {
            $url = $this->largestImageUrl($imageSizes);
            AvitoMessageAttachment::query()->updateOrCreate(
                ['avito_message_id' => $message->id, 'kind' => 'image'],
                ['external_id' => hash('sha256', (string) $url), 'remote_url' => $url]
            );
        }

        $voiceId = trim((string) Arr::get($content, 'voice.voice_id'));
        if ($voiceId !== '') {
            AvitoMessageAttachment::query()->updateOrCreate(
                ['avito_message_id' => $message->id, 'kind' => 'voice'],
                ['external_id' => $voiceId]
            );
        }
    }

    private function largestImageUrl(array $sizes): ?string
    {
        $ranked = collect($sizes)->map(function ($url, $size): array {
            preg_match('/^(\d+)x(\d+)$/', (string) $size, $matches);

            return ['url' => is_string($url) ? $url : null, 'pixels' => (int) ($matches[1] ?? 0) * (int) ($matches[2] ?? 0)];
        })->filter(fn (array $item) => filled($item['url']))->sortByDesc('pixels')->first();

        return $ranked['url'] ?? null;
    }

    private function peerFromUsers(array $users, string $accountId): array
    {
        $peer = collect($users)->first(fn ($user) => (string) Arr::get((array) $user, 'id') !== $accountId);
        $peer = is_array($peer) ? $peer : [];
        $avatars = (array) Arr::get($peer, 'public_user_profile.avatar.images', []);

        return [
            'id' => $this->nullableString(Arr::get($peer, 'id')),
            'name' => $this->nullableString(Arr::get($peer, 'name')),
            'avatar' => Arr::get($avatars, '64x64') ?: Arr::get($peer, 'public_user_profile.avatar.default'),
        ];
    }

    private function preview(array $message): string
    {
        $type = (string) Arr::get($message, 'type', 'unknown');
        $content = (array) Arr::get($message, 'content', []);
        $text = match ($type) {
            'text' => (string) Arr::get($content, 'text', ''),
            'image' => 'Изображение',
            'voice' => 'Голосовое сообщение',
            'location' => (string) (Arr::get($content, 'location.title') ?: 'Геопозиция'),
            'link' => (string) (Arr::get($content, 'link.text') ?: 'Ссылка'),
            'item' => (string) (Arr::get($content, 'item.title') ?: 'Объявление'),
            'call' => 'Звонок',
            'deleted' => 'Сообщение удалено на Avito',
            'system' => 'Системное сообщение',
            default => Str::headline($type ?: 'Сообщение'),
        };

        return Str::limit(trim(strip_tags($text)), 240);
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $value);
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
