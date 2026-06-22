<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SyncYandexMailboxJob;
use App\Models\Email;
use App\Models\MailMessage;
use App\Services\Mail\MailboxRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailMailboxController extends Controller
{
    public function sync(Request $request, MailboxRegistry $mailboxes): JsonResponse
    {
        $data = $request->validate([
                                       'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
                                       'mailbox' => ['nullable', 'email'],
                                   ]);

        if (!empty($data['mailbox']) && !$mailboxes->find($data['mailbox'])) {
            return response()->json([
                'message' => 'Выбранный почтовый ящик не настроен.',
            ], 422);
        }

        SyncYandexMailboxJob::dispatch($data['limit'] ?? 1000, $data['mailbox'] ?? null);

        return response()->json([
                                    'message' => 'Mailbox sync queued',
                                ]);
    }

    public function show(Request $request, Email $email): JsonResponse
    {
        $itemsPerPage = (int) $request->input('itemsPerPage', 25);

        $query = MailMessage::query()
            ->whereHas('emails', fn ($q) => $q->where('emails.id', $email->id))
            ->with(['emails:id,address,name'])
            ->search($request->input('search'))
            ->latest('message_date');

        if ($direction = $request->input('direction')) {
            $query->where('direction', $direction);
        }

        if ($mailbox = $request->input('mailbox')) {
            $query->where('mailbox', mb_strtolower(trim((string) $mailbox)));
        }

        $paginator = $query->paginate(
            perPage: max($itemsPerPage, 1),
            page: (int) $request->input('page', 1)
        );

        return response()->json($paginator);
    }
}
