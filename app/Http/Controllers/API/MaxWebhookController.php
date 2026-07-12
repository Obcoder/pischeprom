<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaxChat;
use App\Models\MaxMessage;
use App\Models\MaxWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaxWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = trim((string) config('services.max.webhook_secret'));

        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Max-Bot-Api-Secret'))) {
            return response()->json([
                'message' => 'Invalid MAX webhook secret.',
            ], 403);
        }

        $payload = $request->all();
        $updates = $this->updates($payload);
        $processed = 0;

        foreach ($updates as $update) {
            $this->storeUpdate($update);
            $processed++;
        }

        return response()->json([
            'ok' => true,
            'processed' => $processed,
        ]);
    }

    private function updates(array $payload): array
    {
        if (isset($payload['updates']) && is_array($payload['updates'])) {
            return array_values($payload['updates']);
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        return [$payload];
    }

    private function storeUpdate(array $payload): void
    {
        $updateType = $this->firstFilled($payload, [
            'update_type',
            'type',
            'event_type',
            'update.update_type',
        ]);
        $updateId = $this->firstFilled($payload, [
            'update_id',
            'id',
            'event_id',
            'update.id',
        ]);
        $chatId = $this->firstFilled($payload, [
            'chat_id',
            'chat.id',
            'chat.chat_id',
            'message.chat_id',
            'message.chat.id',
            'message.recipient.chat_id',
        ]);
        $userId = $this->firstFilled($payload, [
            'user_id',
            'user.id',
            'user.user_id',
            'sender.user_id',
            'message.sender.user_id',
            'message.sender.id',
        ]);
        $phone = $this->extractPhone($payload);
        $title = $this->firstFilled($payload, [
            'chat.title',
            'message.chat.title',
            'title',
        ]);
        $text = $this->firstFilled($payload, [
            'message.body.text',
            'message.text',
            'body.text',
            'text',
        ]);
        $messageId = $this->firstFilled($payload, [
            'message.id',
            'message.mid',
            'message.body.mid',
            'message.message_id',
            'body.mid',
            'message_id',
        ]);

        MaxWebhookEvent::create([
            'update_id' => $updateId ? (string) $updateId : null,
            'update_type' => $updateType ? (string) $updateType : null,
            'phone_normalized' => $phone,
            'chat_id' => $chatId ? (string) $chatId : null,
            'user_id' => $userId ? (string) $userId : null,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        $chat = $this->upsertChat($phone, $chatId, $userId, $title, $payload);

        if ($chat && $text !== null) {
            $this->storeIncomingMessage($chat, $messageId, $text, $payload);
        }
    }

    private function upsertChat(
        ?string $phone,
        mixed $chatId,
        mixed $userId,
        mixed $title,
        array $payload
    ): ?MaxChat {
        $chat = null;

        if ($phone) {
            $chat = MaxChat::query()->where('phone_normalized', $phone)->first();
        }

        if (! $chat && filled($chatId)) {
            $chat = MaxChat::query()->where('chat_id', (string) $chatId)->first();
        }

        if (! $chat && filled($userId)) {
            $chat = MaxChat::query()->where('user_id', (string) $userId)->first();
        }

        $attributes = [
            'phone' => $phone ? '+'.$phone : null,
            'phone_normalized' => $phone,
            'chat_id' => filled($chatId) ? (string) $chatId : null,
            'user_id' => filled($userId) ? (string) $userId : null,
            'title' => filled($title) ? (string) $title : null,
            'source_type' => 'webhook',
            'is_active' => true,
            'last_payload' => $payload,
        ];

        $attributes = array_filter($attributes, fn ($value) => $value !== null);

        if ($chat) {
            $chat->update($attributes);

            return $chat;
        }

        if (empty($attributes['phone_normalized']) && empty($attributes['chat_id']) && empty($attributes['user_id'])) {
            return null;
        }

        return MaxChat::create($attributes);
    }

    private function storeIncomingMessage(MaxChat $chat, mixed $messageId, mixed $text, array $payload): void
    {
        $attributes = [
            'max_chat_id' => $chat->id,
            'max_message_id' => filled($messageId) ? (string) $messageId : null,
            'direction' => MaxMessage::DIRECTION_INCOMING,
            'status' => 'received',
            'phone_normalized' => $chat->phone_normalized,
            'chat_id' => $chat->chat_id,
            'user_id' => $chat->user_id,
            'text' => (string) $text,
            'payload' => $payload,
            'received_at' => now(),
        ];

        if ($attributes['max_message_id']) {
            MaxMessage::query()->updateOrCreate([
                'max_message_id' => $attributes['max_message_id'],
            ], $attributes);
        } else {
            MaxMessage::create($attributes);
        }

        $chat->update([
            'last_message_at' => now(),
            'last_payload' => $payload,
        ]);
    }

    private function firstFilled(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function extractPhone(array $payload): ?string
    {
        $directPhone = MaxChat::normalizePhone($this->firstFilled($payload, [
            'phone',
            'user.phone',
            'contact.phone',
            'message.sender.phone',
            'message.attachments.*.payload.phone',
            'message.attachments.*.payload.max_info.phone',
            'attachments.*.payload.phone',
            'attachments.*.payload.max_info.phone',
        ]));

        if ($directPhone) {
            return $directPhone;
        }

        foreach ($this->filledValues($payload, [
            'message.attachments.*.payload.vcf_info',
            'attachments.*.payload.vcf_info',
        ]) as $vcard) {
            $phone = $this->phoneFromVcard($vcard);

            if ($phone) {
                return $phone;
            }
        }

        return null;
    }

    private function filledValues(array $payload, array $paths): array
    {
        $values = [];

        foreach ($paths as $path) {
            array_push($values, ...$this->flattenFilled(data_get($payload, $path)));
        }

        return $values;
    }

    private function flattenFilled(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item) => $this->flattenFilled($item))
                ->all();
        }

        return filled($value) ? [$value] : [];
    }

    private function phoneFromVcard(mixed $value): ?string
    {
        if (! preg_match_all('/^TEL[^:]*:(.+)$/mi', (string) $value, $matches)) {
            return null;
        }

        foreach ($matches[1] as $phone) {
            $normalized = MaxChat::normalizePhone($phone);

            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }
}
