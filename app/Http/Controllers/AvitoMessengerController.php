<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Jobs\Avito\SyncAvitoMessengerJob;
use App\Models\AvitoChat;
use App\Models\AvitoConnection;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageAttachment;
use App\Models\AvitoMessageTemplate;
use App\Models\AvitoMessengerAccount;
use App\Models\AvitoMessengerSyncRun;
use App\Services\Avito\AvitoContactDetector;
use App\Services\Avito\AvitoMessageTemplateService;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvitoMessengerController extends Controller
{
    public function overview(AvitoApiCatalog $catalog): JsonResponse
    {
        $accounts = AvitoMessengerAccount::query()
            ->with('connection:id,name,is_active')
            ->withCount(['chats', 'chats as unread_chats_count' => fn ($query) => $query->where('is_unread', true)])
            ->latest('id')
            ->get();

        return response()->json([
            'counts' => [
                'accounts' => $accounts->count(),
                'chats' => AvitoChat::query()->count(),
                'unread_chats' => AvitoChat::query()->where('is_unread', true)->count(),
                'messages' => AvitoMessage::query()->count(),
                'attachments' => AvitoMessageAttachment::query()->whereNotNull('archived_at')->count(),
            ],
            'accounts' => $accounts->map(fn (AvitoMessengerAccount $account) => [
                'id' => $account->id,
                'source_key' => $account->source_key,
                'external_user_id' => $account->external_user_id,
                'name' => $account->name,
                'connection_id' => $account->avito_connection_id,
                'connection' => $account->connection?->only(['id', 'name', 'is_active']),
                'sync_enabled' => $account->sync_enabled,
                'sync_status' => $account->sync_status,
                'last_sync_started_at' => $account->last_sync_started_at,
                'last_synced_at' => $account->last_synced_at,
                'last_sync_error' => $account->last_sync_error,
                'chats_count' => $account->chats_count,
                'unread_chats_count' => $account->unread_chats_count,
            ]),
            'latest_runs' => AvitoMessengerSyncRun::query()
                ->with('account:id,name')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (AvitoMessengerSyncRun $run) => $this->serializeRun($run)),
            'tools' => collect($catalog->capabilities())
                ->where('section', 'messenger')
                ->values()
                ->map(fn (array $item) => Arr::only($item, [
                    'id', 'operation_id', 'method', 'path', 'summary', 'description',
                    'access', 'documentation_url',
                ])),
        ]);
    }

    public function chats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:avito_messenger_accounts,id'],
            'search' => ['nullable', 'string', 'max:200'],
            'unread_only' => ['nullable', 'boolean'],
            'chat_type' => ['nullable', 'in:u2i,u2u,a2u'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $query = AvitoChat::query()
            ->with([
                'account:id,name,external_user_id,source_key,avito_connection_id',
                'entity:id,name',
            ])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('remote_updated_at')
            ->orderByDesc('id');

        if (isset($validated['account_id'])) {
            $query->where('avito_messenger_account_id', $validated['account_id']);
        }
        if (! empty($validated['unread_only'])) {
            $query->where('is_unread', true);
        }
        if (! empty($validated['chat_type'])) {
            $query->where('chat_type', $validated['chat_type']);
        }
        if (filled($validated['search'] ?? null)) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($validated['search'])).'%';
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', $search)
                    ->orWhere('peer_name', 'like', $search)
                    ->orWhere('external_chat_id', 'like', $search)
                    ->orWhere('context_id', 'like', $search)
                    ->orWhere('last_message_preview', 'like', $search);
            });
        }

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 40));

        return response()->json($this->mapPaginator($paginator, fn (AvitoChat $chat) => $this->serializeChat($chat)));
    }

    public function chat(
        Request $request,
        AvitoChat $chat,
        AvitoContactDetector $contactDetector,
    ): JsonResponse {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:20', 'max:100'],
        ]);
        $contactDetector->detectChat($chat);
        $chat->load([
            'account:id,name,external_user_id,source_key,avito_connection_id',
            'entity:id,name',
        ]);
        $messages = $chat->messages()
            ->with([
                'attachments',
                'contactCandidates' => fn ($query) => $query
                    ->where('status', 'pending')
                    ->orderByDesc('confidence'),
            ])
            ->orderByDesc('remote_created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 100));
        $serialized = $this->mapPaginator($messages, fn (AvitoMessage $message) => $this->serializeMessage($message));
        $serialized['data'] = array_reverse($serialized['data']);

        return response()->json([
            'chat' => $this->serializeChat($chat),
            'messages' => $serialized,
        ]);
    }

    public function queueSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
            'full' => ['nullable', 'boolean'],
        ]);
        $run = AvitoMessengerSyncRun::query()->create([
            'status' => 'queued',
            'full_sync' => (bool) ($validated['full'] ?? false),
        ]);
        SyncAvitoMessengerJob::dispatchAfterResponse(
            $run->id,
            $validated['connection_id'] ?? null,
            (bool) ($validated['full'] ?? false),
        );

        return response()->json([
            'message' => 'Синхронизация поставлена в обработку.',
            'run' => $this->serializeRun($run),
        ], 202);
    }

    public function syncRun(AvitoMessengerSyncRun $run): JsonResponse
    {
        return response()->json(['run' => $this->serializeRun($run->load('account:id,name'))]);
    }

    public function refreshChat(Request $request, AvitoChat $chat, AvitoMessengerService $messenger): JsonResponse
    {
        $validated = $request->validate([
            'message_limit' => ['nullable', 'integer', 'min:1', 'max:1100'],
        ]);
        $chat = $messenger->refreshChat($chat, (int) ($validated['message_limit'] ?? 100));

        return response()->json([
            'message' => 'Чат и сообщения обновлены из Avito.',
            'chat' => $this->serializeChat($chat),
        ]);
    }

    public function sendText(
        Request $request,
        AvitoChat $chat,
        AvitoMessengerService $messenger,
        AvitoMessageTemplateService $templates,
    ): JsonResponse {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('avito_message_templates', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true)),
            ],
        ]);
        $message = $messenger->sendText($chat, trim($validated['text']));
        if (filled($validated['template_id'] ?? null)) {
            $template = AvitoMessageTemplate::query()->findOrFail($validated['template_id']);
            $templates->recordUsage($template, $chat, $message, trim($validated['text']));
        }

        return response()->json([
            'message' => 'Сообщение отправлено.',
            'item' => $this->serializeMessage($message),
        ], 201);
    }

    public function sendImage(Request $request, AvitoChat $chat, AvitoMessengerService $messenger): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif', 'max:24576'],
        ]);
        $message = $messenger->sendImage($chat, $validated['image']);

        return response()->json([
            'message' => 'Изображение отправлено.',
            'item' => $this->serializeMessage($message),
        ], 201);
    }

    public function destroyMessage(AvitoMessage $message, AvitoMessengerService $messenger): JsonResponse
    {
        $message = $messenger->deleteMessage($message);

        return response()->json([
            'message' => 'Сообщение удалено на Avito; исходное содержимое сохранено в локальном архиве.',
            'item' => $this->serializeMessage($message),
        ]);
    }

    public function markRead(AvitoChat $chat, AvitoMessengerService $messenger): JsonResponse
    {
        $messenger->markRead($chat);

        return response()->json(['message' => 'Чат отмечен прочитанным на Avito.']);
    }

    public function blacklist(Request $request, AvitoChat $chat, AvitoMessengerService $messenger): JsonResponse
    {
        $validated = $request->validate([
            'reason_id' => ['required', 'integer', 'in:1,2,3,4'],
        ]);
        $messenger->blacklistPeer($chat, (int) $validated['reason_id']);

        return response()->json(['message' => 'Пользователь добавлен в чёрный список Avito.']);
    }

    public function subscriptions(Request $request, AvitoMessengerService $messenger): JsonResponse
    {
        return response()->json([
            'items' => $messenger->subscriptions($this->connection($request)),
        ]);
    }

    public function subscribe(Request $request, AvitoMessengerService $messenger): JsonResponse
    {
        $messenger->subscribe($this->connection($request));

        return response()->json(['message' => 'Webhook Messenger V3 подключён.']);
    }

    public function unsubscribe(Request $request, AvitoMessengerService $messenger): JsonResponse
    {
        $messenger->unsubscribe($this->connection($request));

        return response()->json(['message' => 'Webhook Messenger отключён.']);
    }

    public function attachment(AvitoMessageAttachment $attachment): BinaryFileResponse
    {
        if (! $attachment->archived_at || blank($attachment->storage_disk) || blank($attachment->storage_path)) {
            abort(404, 'Вложение ещё не сохранено в локальном архиве.');
        }

        $disk = Storage::disk($attachment->storage_disk);
        if (! $disk->exists($attachment->storage_path)) {
            abort(404, 'Файл локального архива отсутствует.');
        }

        return response()->file($disk->path($attachment->storage_path), [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="avito-attachment-'.$attachment->id.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function connection(Request $request): ?AvitoConnection
    {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);

        return isset($validated['connection_id'])
            ? AvitoConnection::query()->findOrFail($validated['connection_id'])
            : null;
    }

    private function serializeChat(AvitoChat $chat): array
    {
        return [
            'id' => $chat->id,
            'account_id' => $chat->avito_messenger_account_id,
            'account' => $chat->relationLoaded('account') ? $chat->account?->only([
                'id', 'name', 'external_user_id', 'source_key', 'avito_connection_id',
            ]) : null,
            'entity_id' => $chat->entity_id,
            'entity' => $chat->relationLoaded('entity') ? $chat->entity?->only(['id', 'name']) : null,
            'external_chat_id' => $chat->external_chat_id,
            'chat_type' => $chat->chat_type,
            'context_type' => $chat->context_type,
            'context_id' => $chat->context_id,
            'title' => $chat->title,
            'context_url' => $chat->context_url,
            'peer_user_id' => $chat->peer_user_id,
            'peer_name' => $chat->peer_name,
            'peer_avatar_url' => $chat->peer_avatar_url,
            'last_message_id' => $chat->last_message_id,
            'last_message_type' => $chat->last_message_type,
            'last_message_preview' => $chat->last_message_preview,
            'is_unread' => $chat->is_unread,
            'unread_count' => $chat->unread_count,
            'messages_count' => $chat->messages_count ?? null,
            'remote_created_at' => $chat->remote_created_at,
            'remote_updated_at' => $chat->remote_updated_at,
            'last_message_at' => $chat->last_message_at,
            'last_synced_at' => $chat->last_synced_at,
        ];
    }

    private function serializeMessage(AvitoMessage $message): array
    {
        return [
            'id' => $message->id,
            'external_message_id' => $message->external_message_id,
            'author_id' => $message->author_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'remote_type' => $message->remote_type,
            'text' => $message->text,
            'content' => $message->content ?: [],
            'quote' => $message->quote,
            'is_read' => $message->is_read,
            'remote_created_at' => $message->remote_created_at,
            'remote_read_at' => $message->remote_read_at,
            'deleted_from_avito_at' => $message->deleted_from_avito_at,
            'attachments' => $message->relationLoaded('attachments')
                ? $message->attachments->map(fn (AvitoMessageAttachment $attachment) => [
                    'id' => $attachment->id,
                    'kind' => $attachment->kind,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'archived' => $attachment->archived_at !== null,
                    'archive_error' => $attachment->archive_error,
                    'url' => $attachment->archived_at
                        ? route('api.avito.messenger.attachments.show', $attachment)
                        : null,
                ])->values()
                : [],
            'contact_candidates' => $message->relationLoaded('contactCandidates')
                ? $message->contactCandidates->map(fn ($candidate) => [
                    'id' => $candidate->id,
                    'type' => $candidate->type,
                    'raw_value' => $candidate->raw_value,
                    'normalized_value' => $candidate->normalized_value,
                    'confidence' => $candidate->confidence,
                    'status' => $candidate->status,
                ])->values()
                : [],
        ];
    }

    private function serializeRun(AvitoMessengerSyncRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'full_sync' => $run->full_sync,
            'account' => $run->relationLoaded('account') ? $run->account?->only(['id', 'name']) : null,
            'chats_seen' => $run->chats_seen,
            'chats_created' => $run->chats_created,
            'messages_seen' => $run->messages_seen,
            'messages_created' => $run->messages_created,
            'attachments_archived' => $run->attachments_archived,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
            'error_message' => $run->error_message,
            'created_at' => $run->created_at,
        ];
    }

    private function mapPaginator(LengthAwarePaginator $paginator, callable $callback): array
    {
        return [
            'data' => $paginator->getCollection()->map($callback)->values()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
