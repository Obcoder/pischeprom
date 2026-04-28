<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SyncYandexMailboxJob;
use App\Models\Email;
use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailMailboxController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
                                       'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
                                   ]);

        SyncYandexMailboxJob::dispatch($data['limit'] ?? 1000);

        return response()->json([
                                    'message' => 'Yandex mailbox sync queued',
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

        $paginator = $query->paginate(
            perPage: max($itemsPerPage, 1),
            page: (int) $request->input('page', 1)
        );

        return response()->json($paginator);
    }
}
