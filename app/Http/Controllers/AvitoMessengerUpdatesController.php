<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AvitoMessengerUpdatesController extends Controller
{
    public function __invoke(
        Request $request,
        AvitoMessengerController $messenger,
        AvitoApiCatalog $catalog,
    ): JsonResponse {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:avito_messenger_accounts,id'],
            'search' => ['nullable', 'string', 'max:200'],
            'unread_only' => ['nullable', 'boolean'],
            'waiting_only' => ['nullable', 'boolean'],
            'chat_type' => ['nullable', 'in:u2i,u2u,a2u'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'overview' => ['nullable', 'boolean'],
            'chats' => ['nullable', 'boolean'],
            'selected' => ['nullable', 'boolean'],
            'selected_chat_id' => [
                Rule::requiredIf(fn () => $request->boolean('selected')),
                'nullable', 'integer', 'exists:avito_chats,id',
            ],
            'message_ids' => ['nullable', 'array', 'max:200'],
            'message_ids.*' => ['required', 'integer', 'min:1'],
            'after_message_id' => ['nullable', 'integer', 'min:0'],
        ]);

        // One archive snapshot reconciles counters, the filtered page and only
        // changed visible messages. This endpoint never requests data from Avito.
        return DB::transaction(function () use ($request, $validated, $messenger, $catalog): JsonResponse {
            $result = [];
            if ($request->boolean('overview')) {
                $result['overview'] = Arr::except($messenger->overview($catalog)->getData(true), ['tools']);
            }
            if ($request->boolean('chats')) {
                $filters = Arr::only($validated, ['account_id', 'search', 'unread_only', 'waiting_only', 'chat_type', 'page', 'per_page']);
                $chatRequest = $request->duplicate($filters);
                $result['chats'] = $messenger->chats($chatRequest)->getData(true);
            }
            if ($request->boolean('selected')) {
                $chat = AvitoChat::query()
                    ->with([
                        'account:id,name,external_user_id,source_key,avito_connection_id',
                        'entity:id,name',
                    ])
                    ->withCount('messages')
                    ->findOrFail($validated['selected_chat_id']);
                $messageIds = array_values(array_unique(array_map('intval', $validated['message_ids'] ?? [])));
                $existingMessageIds = $chat->messages()->whereKey($messageIds)->pluck('id')->all();
                $messages = $chat->messages()
                    ->where(function ($query) use ($messageIds, $validated): void {
                        $query->whereKey($messageIds);
                        if (isset($validated['after_message_id'])) {
                            $query->orWhere('id', '>', (int) $validated['after_message_id']);
                        }
                    })
                    ->with([
                        'attachments',
                        'contactCandidates' => fn ($query) => $query
                            ->where('status', 'pending')
                            ->orderByDesc('confidence'),
                    ])
                    ->orderBy('remote_created_at')
                    ->orderBy('id')
                    ->limit(201)
                    ->get();
                $result['selected'] = [
                    'chat' => $messenger->serializeChat($chat),
                    'messages' => $messages->take(200)->map(fn (AvitoMessage $message) => $messenger->serializeMessage($message))->all(),
                    'missing_message_ids' => array_values(array_diff($messageIds, $existingMessageIds)),
                    'has_more' => $messages->count() > 200,
                ];
            }

            return response()->json($result);
        });
    }
}
