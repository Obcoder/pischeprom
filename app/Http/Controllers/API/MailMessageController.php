<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MailMessage;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\MailDeletionException;
use App\Services\Mail\MailWorkspaceAccess;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MailMessageController extends Controller
{
    public function mailboxes(MailboxRegistry $mailboxes): JsonResponse
    {
        return response()->json([
            'data' => $mailboxes->publicMailboxes(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'filters' => ['sometimes', 'array'],
            'filters.date_from' => ['nullable', 'date_format:Y-m-d'],
            'filters.date_to' => [
                'nullable',
                'date_format:Y-m-d',
                ...($request->filled('filters.date_from') ? ['after_or_equal:filters.date_from'] : []),
            ],
        ]);

        $itemsPerPage = (int) $request->input('itemsPerPage', 25);

        $query = MailMessage::query()
            ->select([
                'id',
                'mailbox',
                'folder',
                'direction',
                'imap_uid',
                'message_id',
                'reply_to_mail_message_id',
                'in_reply_to',
                'references',
                'subject',
                'message_date',
                'from_address',
                'from_name',
                'to',
                'cc',
                'preview',
                'has_attachments',
                'is_seen',
                'body_loaded_at',
                'created_at',
                'updated_at',
            ])
            ->with($this->messageRelations())
            ->withCount(['attachments', 'notes', 'leads'])
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
        try {
            $mailMessage = $service->loadBody(
                mailMessage: $mailMessage,
                force: $request->boolean('force'),
                withAttachments: false,
                includeAttachmentList: true,
            );
        } catch (Throwable $exception) {
            report($exception);

            $mailMessage->setAttribute(
                'mail_sync_error',
                'Не удалось обновить письмо из Yandex IMAP. Показаны сохранённые данные.'
            );
        }

        $mailMessage->load($this->messageRelations());

        return response()->json($mailMessage, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function destroy(Request $request, MailMessage $mailMessage, YandexMailboxService $service, MailWorkspaceAccess $access): JsonResponse
    {
        $access->authorize($request->user());
        try {
            $service->deleteMessage($mailMessage);
        } catch (MailDeletionException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Не удалось подтвердить удаление на почтовом сервере. Письмо оставлено в приложении. Повторите попытку.'], 502);
        }

        return response()->json([
            'message' => 'Письмо удалено из приложения и с почтового сервера.',
        ]);
    }

    protected function messageRelations(): array
    {
        return [
            'emails' => fn ($query) => $query->select('emails.id', 'emails.address', 'emails.name'),
            'emails.units' => fn ($query) => $query
                ->without(['fields', 'labels', 'telephones', 'uris'])
                ->select('units.id', 'units.name'),
            'emails.entities' => fn ($query) => $query
                ->without(['buildings', 'classification', 'country'])
                ->select('entities.id', 'entities.name'),
            'emails.entities.units' => fn ($query) => $query
                ->without(['fields', 'labels', 'telephones', 'uris'])
                ->select('units.id', 'units.name'),
            'attachments',
            'notes.user:id,name',
            'leads:id,mail_message_id,title,status,entity_id,unit_id',
        ];
    }
}
