<?php

namespace App\Http\Controllers;

use App\Models\AvitoChat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvitoWaitingListController extends Controller
{
    public function index(Request $request, AvitoMessengerController $messenger): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = AvitoChat::query()
            ->whereNotNull('waiting_since')
            ->with(['account:id,name,external_user_id,source_key,avito_connection_id', 'entity:id,name'])
            ->withCount('messages')
            ->orderBy('waiting_since')
            ->orderBy('id');

        if (filled($validated['search'] ?? null)) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($validated['search'])).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', $search)
                    ->orWhere('peer_name', 'like', $search)
                    ->orWhere('peer_user_id', 'like', $search)
                    ->orWhere('external_chat_id', 'like', $search)
                    ->orWhere('context_id', 'like', $search)
                    ->orWhere('last_message_preview', 'like', $search)
                    ->orWhere('waiting_note', 'like', $search)
                    ->orWhereHas('account', function (Builder $account) use ($search): void {
                        $account->where('name', 'like', $search)
                            ->orWhere('external_user_id', 'like', $search);
                    });
            });
        }

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 10));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AvitoChat $chat) => $messenger->serializeChat($chat))->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(Request $request, AvitoChat $chat, AvitoMessengerController $messenger): JsonResponse
    {
        $validated = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $chat = DB::transaction(function () use ($chat, $validated): AvitoChat {
            $chat = AvitoChat::query()->lockForUpdate()->findOrFail($chat->id);
            $chat->waiting_since ??= now();
            if (array_key_exists('note', $validated)) {
                $chat->waiting_note = $validated['note'];
            }
            $chat->save();

            return $chat;
        });

        return $this->chatResponse($chat, $messenger);
    }

    public function update(Request $request, AvitoChat $chat, AvitoMessengerController $messenger): JsonResponse
    {
        $validated = $request->validate(['note' => ['present', 'nullable', 'string', 'max:2000']]);
        $chat = DB::transaction(function () use ($chat, $validated): AvitoChat {
            $chat = AvitoChat::query()->lockForUpdate()->findOrFail($chat->id);
            abort_if($chat->waiting_since === null, 404, 'Чат не находится в листе ожидания.');
            $chat->update(['waiting_note' => $validated['note']]);

            return $chat;
        });

        return $this->chatResponse($chat, $messenger);
    }

    public function destroy(AvitoChat $chat, AvitoMessengerController $messenger): JsonResponse
    {
        $chat = DB::transaction(function () use ($chat): AvitoChat {
            $chat = AvitoChat::query()->lockForUpdate()->findOrFail($chat->id);
            $chat->update(['waiting_since' => null, 'waiting_note' => null]);

            return $chat;
        });

        return $this->chatResponse($chat, $messenger);
    }

    private function chatResponse(AvitoChat $chat, AvitoMessengerController $messenger): JsonResponse
    {
        $chat->load(['account:id,name,external_user_id,source_key,avito_connection_id', 'entity:id,name'])->loadCount('messages');

        return response()->json(['chat' => $messenger->serializeChat($chat)]);
    }
}
