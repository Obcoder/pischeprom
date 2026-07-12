<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaxChat;
use App\Models\MaxMessage;
use App\Services\MaxMessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaxChatController extends Controller
{
    public function __construct(private readonly MaxMessengerService $max)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 80), 1), 200);
        $phone = MaxChat::normalizePhone($request->input('phone'));

        $query = MaxChat::query()
            ->with(['entity:id,name', 'unit:id,name'])
            ->withCount('messages')
            ->search($request->string('search')->toString())
            ->when($phone, fn ($q) => $q->where('phone_normalized', $phone))
            ->orderByDesc('last_message_at')
            ->latest('id');

        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->limit(500)->get()->map(fn (MaxChat $chat) => $this->chatPayload($chat)),
            ]);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (MaxChat $chat) => $this->chatPayload($chat)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedChatData($request);
        $chat = $this->resolveChat($data);
        $attributes = $this->chatAttributes($data);

        if (! $this->hasAnyTarget($attributes)) {
            return response()->json([
                'message' => 'Укажите телефон, chat_id или user_id MAX.',
            ], 422);
        }

        if ($chat) {
            $this->guardUniquePhone($attributes['phone_normalized'] ?? null, $chat);
            $chat->update($attributes);
        } else {
            $chat = MaxChat::create($attributes);
        }

        return response()->json([
            'data' => $this->chatPayload($chat->fresh(['entity:id,name', 'unit:id,name'])),
        ], $chat->wasRecentlyCreated ? 201 : 200);
    }

    public function show(MaxChat $maxChat): JsonResponse
    {
        $maxChat->load(['entity:id,name', 'unit:id,name'])
            ->loadCount('messages');

        return response()->json([
            'data' => $this->chatPayload($maxChat),
        ]);
    }

    public function update(Request $request, MaxChat $maxChat): JsonResponse
    {
        $data = $this->validatedChatData($request, false);
        $attributes = $this->chatAttributes($data);

        if (array_key_exists('phone_normalized', $attributes)) {
            $this->guardUniquePhone($attributes['phone_normalized'], $maxChat);
        }

        $maxChat->update($attributes);

        return response()->json([
            'data' => $this->chatPayload($maxChat->fresh(['entity:id,name', 'unit:id,name'])),
        ]);
    }

    public function destroy(MaxChat $maxChat): JsonResponse
    {
        $maxChat->delete();

        return response()->json([
            'message' => 'MAX-чат удалён из локальной базы.',
        ]);
    }

    public function messages(Request $request, MaxChat $maxChat): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 60), 1), 200);
        $messages = $maxChat->messages()
            ->limit($limit)
            ->get()
            ->map(fn (MaxMessage $message) => $this->messagePayload($message));

        $remote = null;

        if ($request->boolean('remote') && filled($maxChat->chat_id)) {
            $remote = $this->max->getMessages([
                'chat_id' => $maxChat->chat_id,
                'limit' => $limit,
            ]);
        }

        return response()->json([
            'data' => $messages,
            'remote' => $remote,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'max_chat_id' => ['nullable', 'integer', 'exists:max_chats,id'],
            'phone' => ['nullable', 'string', 'max:64'],
            'chat_id' => ['nullable', 'string', 'max:96'],
            'user_id' => ['nullable', 'string', 'max:96'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:4000'],
        ]);

        $chat = $this->resolveChat($data);
        $attributes = $this->chatAttributes($data);

        if ($chat) {
            $chat->fill(array_filter($attributes, fn ($value) => filled($value) || is_bool($value)));
            $chat->save();
        } else {
            $chat = MaxChat::create([
                ...$attributes,
                'source_type' => 'manual',
            ]);
        }

        $target = $this->messageTarget($chat);

        if (empty($target)) {
            return response()->json([
                'message' => 'Для этого телефона ещё не указан MAX chat_id или user_id. Сохраните привязку чата.',
                'data' => $this->chatPayload($chat->fresh(['entity:id,name', 'unit:id,name'])),
            ], 422);
        }

        $result = $this->max->sendMessage($target, $data['text']);
        $message = $this->storeOutgoingMessage($chat, $data['text'], $result);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['error'] ?: 'MAX не принял сообщение.',
                'data' => $this->chatPayload($chat->fresh(['entity:id,name', 'unit:id,name'])),
                'max_message' => $this->messagePayload($message),
                'provider' => $result,
            ], 502);
        }

        return response()->json([
            'data' => $this->chatPayload($chat->fresh(['entity:id,name', 'unit:id,name'])),
            'max_message' => $this->messagePayload($message),
            'provider' => $result,
        ], 201);
    }

    public function updateMessage(Request $request, MaxMessage $maxMessage): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        if (! $maxMessage->max_message_id) {
            return response()->json([
                'message' => 'У локального сообщения нет MAX message_id для редактирования.',
            ], 422);
        }

        $result = $this->max->editMessage($maxMessage->max_message_id, $data['text']);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['error'] ?: 'MAX не обновил сообщение.',
                'provider' => $result,
            ], 502);
        }

        $maxMessage->update([
            'text' => $data['text'],
            'status' => 'edited',
            'payload' => $result['data'],
        ]);

        return response()->json([
            'data' => $this->messagePayload($maxMessage->fresh()),
            'provider' => $result,
        ]);
    }

    public function destroyMessage(Request $request, MaxMessage $maxMessage): JsonResponse
    {
        if ($request->boolean('remote', true) && $maxMessage->max_message_id) {
            $result = $this->max->deleteMessage($maxMessage->max_message_id);

            if (! $result['ok']) {
                return response()->json([
                    'message' => $result['error'] ?: 'MAX не удалил сообщение.',
                    'provider' => $result,
                ], 502);
            }
        }

        $maxMessage->update([
            'status' => 'deleted',
        ]);

        return response()->json([
            'data' => $this->messagePayload($maxMessage->fresh()),
        ]);
    }

    public function sync(MaxChat $maxChat): JsonResponse
    {
        if (! $maxChat->chat_id) {
            return response()->json([
                'message' => 'У чата не указан chat_id.',
            ], 422);
        }

        $result = $this->max->getChat($maxChat->chat_id);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['error'] ?: 'MAX не вернул данные чата.',
                'provider' => $result,
            ], 502);
        }

        $maxChat->update([
            'title' => data_get($result['data'], 'title') ?: data_get($result['data'], 'chat.title') ?: $maxChat->title,
            'last_payload' => $result['data'],
        ]);

        return response()->json([
            'data' => $this->chatPayload($maxChat->fresh(['entity:id,name', 'unit:id,name'])),
            'provider' => $result,
        ]);
    }

    private function validatedChatData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'phone' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:64'],
            'phone_normalized' => ['nullable', 'string', 'max:32'],
            'chat_id' => ['nullable', 'string', 'max:96'],
            'user_id' => ['nullable', 'string', 'max:96'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function chatAttributes(array $data): array
    {
        $phone = $data['phone'] ?? $data['phone_normalized'] ?? null;
        $normalized = MaxChat::normalizePhone($phone);

        return collect([
            'phone' => $data['phone'] ?? ($normalized ? '+'.$normalized : null),
            'phone_normalized' => $normalized,
            'chat_id' => $data['chat_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'title' => $data['title'] ?? null,
            'source_type' => $data['source_type'] ?? 'manual',
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ])->reject(fn ($value, $key) => ! in_array($key, ['phone', 'phone_normalized'], true) && $value === null)
            ->all();
    }

    private function resolveChat(array $data): ?MaxChat
    {
        if (! empty($data['max_chat_id'])) {
            return MaxChat::find($data['max_chat_id']);
        }

        $phone = MaxChat::normalizePhone($data['phone'] ?? $data['phone_normalized'] ?? null);

        if ($phone) {
            $chat = MaxChat::query()->where('phone_normalized', $phone)->first();

            if ($chat) {
                return $chat;
            }
        }

        foreach (['chat_id', 'user_id'] as $key) {
            if (! empty($data[$key])) {
                $chat = MaxChat::query()->where($key, $data[$key])->first();

                if ($chat) {
                    return $chat;
                }
            }
        }

        return null;
    }

    private function guardUniquePhone(?string $phone, MaxChat $chat): void
    {
        if (! $phone) {
            return;
        }

        abort_if(
            MaxChat::query()
                ->where('phone_normalized', $phone)
                ->whereKeyNot($chat->getKey())
                ->exists(),
            422,
            'Этот телефон уже привязан к другому MAX-чату.'
        );
    }

    private function hasAnyTarget(array $attributes): bool
    {
        return filled($attributes['phone_normalized'] ?? null)
            || filled($attributes['chat_id'] ?? null)
            || filled($attributes['user_id'] ?? null);
    }

    private function messageTarget(MaxChat $chat): array
    {
        if (filled($chat->chat_id)) {
            return ['chat_id' => $chat->chat_id];
        }

        if (filled($chat->user_id)) {
            return ['user_id' => $chat->user_id];
        }

        return [];
    }

    private function storeOutgoingMessage(MaxChat $chat, string $text, array $result): MaxMessage
    {
        $payload = $result['data'] ?: [];
        $messageId = $this->extractMessageId($payload);

        $message = $chat->messages()->create([
            'max_message_id' => $messageId,
            'direction' => MaxMessage::DIRECTION_OUTGOING,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'phone_normalized' => $chat->phone_normalized,
            'chat_id' => $chat->chat_id,
            'user_id' => $chat->user_id,
            'text' => $text,
            'error_message' => $result['ok'] ? null : $result['error'],
            'payload' => $payload,
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        if ($result['ok']) {
            $chat->update([
                'last_message_at' => now(),
                'last_payload' => $payload,
            ]);
        }

        return $message;
    }

    private function extractMessageId(array $payload): ?string
    {
        foreach ([
            'message_id',
            'message.id',
            'message.mid',
            'message.body.mid',
            'body.mid',
            'mid',
            'id',
        ] as $path) {
            $value = data_get($payload, $path);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function chatPayload(MaxChat $chat): array
    {
        return [
            'id' => $chat->id,
            'phone' => $chat->phone,
            'phone_normalized' => $chat->phone_normalized,
            'chat_id' => $chat->chat_id,
            'user_id' => $chat->user_id,
            'entity_id' => $chat->entity_id,
            'unit_id' => $chat->unit_id,
            'contact_name' => $chat->contact_name,
            'title' => $chat->title,
            'source_type' => $chat->source_type,
            'is_active' => (bool) $chat->is_active,
            'last_message_at' => $chat->last_message_at?->toISOString(),
            'messages_count' => $chat->messages_count ?? null,
            'entity' => $chat->relationLoaded('entity') && $chat->entity ? [
                'id' => $chat->entity->id,
                'name' => $chat->entity->name,
            ] : null,
            'unit' => $chat->relationLoaded('unit') && $chat->unit ? [
                'id' => $chat->unit->id,
                'name' => $chat->unit->name,
            ] : null,
            'created_at' => $chat->created_at?->toISOString(),
            'updated_at' => $chat->updated_at?->toISOString(),
        ];
    }

    private function messagePayload(MaxMessage $message): array
    {
        return [
            'id' => $message->id,
            'max_chat_id' => $message->max_chat_id,
            'max_message_id' => $message->max_message_id,
            'direction' => $message->direction,
            'status' => $message->status,
            'phone_normalized' => $message->phone_normalized,
            'chat_id' => $message->chat_id,
            'user_id' => $message->user_id,
            'text' => $message->text,
            'error_message' => $message->error_message,
            'sent_at' => $message->sent_at?->toISOString(),
            'received_at' => $message->received_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];
    }
}
