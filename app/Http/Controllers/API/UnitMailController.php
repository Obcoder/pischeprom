<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\SendUnitMailRequest;
use App\Models\MailMessage;
use App\Models\Unit;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailDispatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnitMailController extends Controller
{
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $emailIds = $this->collectUnitEmailIds($unit);

        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        $query = MailMessage::query();

        if (empty($emailIds)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('id', function ($subQuery) use ($emailIds) {
                $subQuery
                    ->select('mail_message_id')
                    ->from('email_mail_message')
                    ->whereIn('email_id', $emailIds);
            });
        }

        if ($direction = $request->input('direction')) {
            $query->where('direction', $direction);
        }

        if ($mailbox = $request->input('mailbox')) {
            $query->where('mailbox', mb_strtolower(trim((string) $mailbox)));
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";

                $q->where('subject', 'like', $like)
                    ->orWhere('from_address', 'like', $like)
                    ->orWhere('from_name', 'like', $like)
                    ->orWhere('message_id', 'like', $like)
                    ->orWhere('preview', 'like', $like)
                    ->orWhere('text', 'like', $like)
                    ->orWhere('html', 'like', $like)
                    ->orWhereHas('emails', fn ($sq) => $sq->where('address', 'like', $like));
            });
        }

        if (Schema::hasColumn('mail_messages', 'message_date')) {
            $query->orderByDesc('message_date');
        }

        $query->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator
                ->getCollection()
                ->map(fn (MailMessage $message) => $this->serializeMailMessage($message))
                ->values(),

            'related_emails' => $this->collectUnitRecipientEmails($unit),

            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function collectUnitRecipientEmails(Unit $unit): array
    {
        $unit->loadMissing([
            'emails',
            'entities.emails',
        ]);

        $result = collect();

        foreach ($unit->emails as $email) {
            if (! $email?->address) {
                continue;
            }

            $result->push([
                'id' => $email->id,
                'address' => $email->address,
                'name' => $email->name ?? null,
                'source' => 'unit',
                'source_label' => 'Unit',
                'entity_id' => null,
                'entity_name' => null,
            ]);
        }

        foreach ($unit->entities as $entity) {
            foreach ($entity->emails as $email) {
                if (! $email?->address) {
                    continue;
                }

                $result->push([
                    'id' => $email->id,
                    'address' => $email->address,
                    'name' => $email->name ?? null,
                    'source' => 'entity',
                    'source_label' => 'Entity: '.$entity->name,
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->name,
                ]);
            }
        }

        return $result
            ->filter(fn ($email) => filter_var($email['address'], FILTER_VALIDATE_EMAIL))
            ->unique(fn ($email) => mb_strtolower($email['address']))
            ->values()
            ->all();
    }

    public function send(
        SendUnitMailRequest $request,
        Unit $unit,
        AuthorizedMailDispatchService $dispatch,
    ): JsonResponse {
        try {
            // Deliberate controller-level check; the service repeats it immediately before dispatch.
            $dispatch->authorize($request->user(), $unit);
            $data = $request->validated();
            $data['attachments'] = $request->file('attachments', []);
            $result = $dispatch->dispatchMessage($request->user(), $data, 'api.units.mail.send', $unit);

            return response()->json([
                'message' => $result['duplicate'] ? 'Письмо уже обработано.' : 'Письмо отправлено.',
                'duplicate' => $result['duplicate'],
                'stored_locally' => $result['mail_message'] !== null,
                'mail_message' => $result['mail_message'] ? $this->serializeMailMessage($result['mail_message']) : null,
            ]);
        } catch (MailDispatchException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->safeCode], $exception->httpStatus);
        }
    }

    private function collectUnitEmailIds(Unit $unit): array
    {
        $directEmailIds = DB::table('email_unit')
            ->where('unit_id', $unit->id)
            ->pluck('email_id')
            ->all();

        $entityEmailIds = [];

        if (Schema::hasTable('email_entity') && Schema::hasTable('entity_unit')) {
            $entityEmailIds = DB::table('email_entity')
                ->join('entity_unit', 'entity_unit.entity_id', '=', 'email_entity.entity_id')
                ->where('entity_unit.unit_id', $unit->id)
                ->pluck('email_entity.email_id')
                ->all();
        }

        return collect($directEmailIds)
            ->merge($entityEmailIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function serializeMailMessage(MailMessage $message): array
    {
        return [
            'id' => $message->id,

            'mailbox' => $message->getAttribute('mailbox'),
            'folder' => $message->getAttribute('folder'),
            'direction' => $message->getAttribute('direction'),

            'imap_uid' => $message->getAttribute('imap_uid'),
            'message_id' => $message->getAttribute('message_id'),
            'reply_to_mail_message_id' => $message->getAttribute('reply_to_mail_message_id'),
            'in_reply_to' => $message->getAttribute('in_reply_to'),
            'references' => $message->getAttribute('references'),

            'subject' => $message->getAttribute('subject'),
            'preview' => $message->getAttribute('preview'),

            'from_address' => $message->getAttribute('from_address'),
            'from_name' => $message->getAttribute('from_name'),

            'to' => $this->decodeRecipients($message->getAttribute('to')),
            'cc' => $this->decodeRecipients($message->getAttribute('cc')),

            'text' => $message->getAttribute('text'),
            'html' => $message->getAttribute('html'),

            'has_attachments' => (bool) $message->getAttribute('has_attachments'),
            'body_loaded_at' => $message->getAttribute('body_loaded_at'),

            'message_date' => $message->getAttribute('message_date'),
            'created_at' => $message->getAttribute('created_at'),
            'updated_at' => $message->getAttribute('updated_at'),
        ];
    }

    private function decodeRecipients(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! $value) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
