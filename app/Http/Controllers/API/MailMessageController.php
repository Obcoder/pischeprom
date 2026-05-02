<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MailMessage;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $itemsPerPage = (int) $request->input('itemsPerPage', 25);

        $query = MailMessage::query()
            ->select([
                         'id',
                         'mailbox',
                         'folder',
                         'direction',
                         'imap_uid',
                         'message_id',
                         'subject',
                         'message_date',
                         'from_address',
                         'from_name',
                         'to',
                         'cc',
                         'preview',
                         'has_attachments',
                         'body_loaded_at',
                         'created_at',
                         'updated_at',
                     ])
            ->with(['emails:id,address,name'])
            ->search($request->input('search'))
            ->filter($request->input('filters', []))
            ->orderByDesc('message_date')
            ->orderByDesc('id');

        if ($itemsPerPage === -1) {
            $items = $query->get();

            return response()->json([
                                        'data' => $items,
                                        'total' => $items->count(),
                                    ]);
        }

        return response()->json(
            $query->paginate(
                perPage: max($itemsPerPage, 1),
                page: (int) $request->input('page', 1)
            )
        );
    }

    public function folders(): JsonResponse
    {
        $folders = MailMessage::query()
            ->whereNotNull('folder')
            ->select('folder')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder')
            ->map(fn ($folder) => [
                'title' => $folder,
                'value' => $folder,
            ])
            ->prepend([
                          'title' => 'Все',
                          'value' => null,
                      ])
            ->values();

        return response()->json($folders);
    }

    public function show(
        Request $request,
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        $mailMessage = $service->loadBody(
            mailMessage: $mailMessage,
            force: $request->boolean('force')
        );

        $mailMessage->load(['emails:id,address,name']);

        return response()->json($mailMessage);
    }
}
