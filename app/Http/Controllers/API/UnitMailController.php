<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\MailMessage;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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
                                    'meta' => [
                                        'current_page' => $paginator->currentPage(),
                                        'last_page' => $paginator->lastPage(),
                                        'per_page' => $paginator->perPage(),
                                        'total' => $paginator->total(),
                                    ],
                                ]);
    }

    public function send(Request $request, Unit $unit): JsonResponse
    {
        $request->validate([
                               'subject' => ['nullable', 'string', 'max:998'],
                               'body' => ['nullable', 'string'],

                               'to' => ['required'],
                               'cc' => ['nullable'],
                               'bcc' => ['nullable'],

                               'attachments' => ['nullable'],
                               'attachments.*' => ['file', 'max:51200'],

                               'storage_files' => ['nullable'],
                               'storage_files.*' => ['nullable'],
                           ]);

        $to = $this->normalizeRecipients($request->input('to'));
        $cc = $this->normalizeRecipients($request->input('cc'));
        $bcc = $this->normalizeRecipients($request->input('bcc'));

        if (empty($to)) {
            return response()->json([
                                        'message' => 'Не указан получатель письма.',
                                    ], 422);
        }

        $subject = trim((string) $request->input('subject'));

        if ($subject === '') {
            $subject = '(без темы)';
        }

        $body = (string) $request->input('body', '');
        $bodyHtml = nl2br(e($body));

        $fromAddress = $this->defaultFromAddress();
        $fromName = $this->defaultFromName();

        $localAttachments = $this->normalizeUploadedFiles($request->file('attachments'));
        $storageFiles = $this->normalizeStorageFiles($request->input('storage_files'));
        $storageAttachments = $this->buildStorageAttachments($storageFiles);

        Log::info('Unit mail send endpoint reached', [
            'unit_id' => $unit->id,
            'unit_name' => $unit->name,
            'payload_keys' => array_keys($request->all()),
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'has_local_attachments' => $localAttachments->isNotEmpty(),
            'storage_files' => $storageFiles,
        ]);

        try {
            Mail::html($bodyHtml, function ($message) use (
                $to,
                $cc,
                $bcc,
                $subject,
                $fromAddress,
                $fromName,
                $localAttachments,
                $storageAttachments
            ) {
                $message->to($to);

                if (!empty($cc)) {
                    $message->cc($cc);
                }

                if (!empty($bcc)) {
                    $message->bcc($bcc);
                }

                if ($fromAddress) {
                    $message->from($fromAddress, $fromName);
                }

                $message->subject($subject);

                foreach ($localAttachments as $file) {
                    $message->attach($file->getRealPath(), [
                        'as' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    ]);
                }

                foreach ($storageAttachments as $attachment) {
                    $message->attachData(
                        $attachment['data'],
                        $attachment['name'],
                        [
                            'mime' => $attachment['mime'],
                        ]
                    );
                }
            });
        } catch (Throwable $exception) {
            Log::error('Unit mail send failed', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'to' => $to,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                                        'message' => 'Письмо не отправлено.',
                                        'error' => $exception->getMessage(),
                                    ], 500);
        }

        $storedMessage = null;
        $stored = false;

        try {
            $storedMessage = $this->recordLocalSentMessage(
                unit: $unit,
                fromAddress: $fromAddress,
                fromName: $fromName,
                to: $to,
                cc: $cc,
                bcc: $bcc,
                subject: $subject,
                body: $body,
                bodyHtml: $bodyHtml,
            );

            $stored = (bool) $storedMessage;
        } catch (Throwable $exception) {
            Log::error('Unit mail sent, but local mail message was not stored', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'to' => $to,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }

        Log::info('Unit mail sent successfully', [
            'unit_id' => $unit->id,
            'unit_name' => $unit->name,
            'to' => $to,
            'subject' => $subject,
            'local_attachments_count' => $localAttachments->count(),
            'storage_files_count' => count($storageAttachments),
            'stored_locally' => $stored,
            'mail_message_id' => $storedMessage?->id,
        ]);

        return response()->json([
                                    'message' => 'Письмо отправлено.',
                                    'stored_locally' => $stored,
                                    'mail_message' => $storedMessage
                                        ? $this->serializeMailMessage($storedMessage)
                                        : null,
                                ]);
    }

    private function recordLocalSentMessage(
        Unit $unit,
        ?string $fromAddress,
        ?string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $body,
        string $bodyHtml,
    ): ?MailMessage {
        if (!Schema::hasTable('mail_messages')) {
            return null;
        }

        $mailMessage = new MailMessage();

        $payload = [
            'mailbox' => $fromAddress,
            'folder' => 'Sent',
            'direction' => 'outgoing',
            'imap_uid' => null,
            'message_id' => $this->generateLocalMessageId(),

            'subject' => $subject,
            'preview' => Str::limit(trim(strip_tags($body)), 250),

            'from_address' => $fromAddress,
            'from_name' => $fromName,

            'to' => $this->addressesToRecipients($to),
            'cc' => $this->addressesToRecipients($cc),

            'text' => $body,
            'html' => $bodyHtml,

            'message_date' => now(),
            'body_loaded_at' => now(),
            'has_attachments' => false,
        ];

        $payload = collect($payload)
            ->filter(fn ($value, string $column) => Schema::hasColumn('mail_messages', $column))
            ->all();

        $payload = $this->prepareJsonColumnsForModel($mailMessage, $payload);

        $mailMessage->forceFill($payload);
        $mailMessage->save();

        $recipientAddresses = collect([$to, $cc, $bcc])
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        $recipientEmailIds = Email::query()
            ->whereIn('address', $recipientAddresses)
            ->pluck('id')
            ->all();

        $senderEmailId = null;

        if ($fromAddress) {
            $senderEmailId = Email::query()
                ->where('address', $fromAddress)
                ->value('id');
        }

        if ($senderEmailId) {
            $this->attachEmailToMailMessage($mailMessage->id, (int) $senderEmailId, 'from');
        }

        foreach ($recipientEmailIds as $emailId) {
            $this->attachEmailToMailMessage($mailMessage->id, (int) $emailId, 'to');
        }

        foreach ($this->collectUnitEmailIds($unit) as $emailId) {
            $this->attachEmailToMailMessage($mailMessage->id, (int) $emailId, 'related');
        }

        return $mailMessage;
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

    private function attachEmailToMailMessage(int $mailMessageId, int $emailId, string $role = 'related'): void
    {
        if (!Schema::hasTable('email_mail_message')) {
            return;
        }

        $query = DB::table('email_mail_message')
            ->where('email_id', $emailId)
            ->where('mail_message_id', $mailMessageId);

        if (Schema::hasColumn('email_mail_message', 'role')) {
            $query->where('role', $role);
        }

        $exists = $query->exists();

        if ($exists) {
            $update = [];

            if (Schema::hasColumn('email_mail_message', 'updated_at')) {
                $update['updated_at'] = now();
            }

            if (!empty($update)) {
                DB::table('email_mail_message')
                    ->where('email_id', $emailId)
                    ->where('mail_message_id', $mailMessageId)
                    ->when(
                        Schema::hasColumn('email_mail_message', 'role'),
                        fn ($q) => $q->where('role', $role)
                    )
                    ->update($update);
            }

            return;
        }

        $payload = [
            'email_id' => $emailId,
            'mail_message_id' => $mailMessageId,
        ];

        if (Schema::hasColumn('email_mail_message', 'role')) {
            $payload['role'] = $role;
        }

        if (Schema::hasColumn('email_mail_message', 'created_at')) {
            $payload['created_at'] = now();
        }

        if (Schema::hasColumn('email_mail_message', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('email_mail_message')->insert($payload);
    }

    private function normalizeRecipients(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/[,;\s]+/', $value);
            }
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['address'] ?? $item['email'] ?? null;
                }

                if (is_object($item)) {
                    return $item->address ?? $item->email ?? null;
                }

                return $item;
            })
            ->map(fn ($address) => trim((string) $address))
            ->filter(fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeUploadedFiles(mixed $files): \Illuminate\Support\Collection
    {
        if (!$files) {
            return collect();
        }

        if ($files instanceof UploadedFile) {
            return collect([$files]);
        }

        if (!is_array($files)) {
            return collect();
        }

        return collect($files)
            ->flatten()
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();
    }

    private function normalizeStorageFiles(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [$value];
            }
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['path'] ?? $item['key'] ?? null;
                }

                if (is_object($item)) {
                    return $item->path ?? $item->key ?? null;
                }

                return $item;
            })
            ->map(fn ($path) => $this->normalizeStoragePath($path))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeStoragePath(mixed $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = $urlPath ? ltrim($urlPath, '/') : '';
        }

        $path = strtok($path, '?') ?: $path;
        $path = ltrim($path, '/');

        $bucket = (string) config('filesystems.disks.yandex.bucket');

        if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
            $path = Str::after($path, $bucket . '/');
        }

        return $path !== '' ? $path : null;
    }

    private function buildStorageAttachments(array $storageFiles): array
    {
        $attachments = [];
        $disk = Storage::disk('yandex');

        foreach ($storageFiles as $path) {
            try {
                if (!$disk->exists($path)) {
                    Log::warning('Yandex storage attachment not found', [
                        'path' => $path,
                    ]);

                    continue;
                }

                $data = $disk->get($path);

                if ($data === null || $data === false) {
                    Log::warning('Yandex storage attachment is empty or unreadable', [
                        'path' => $path,
                    ]);

                    continue;
                }

                $attachments[] = [
                    'path' => $path,
                    'name' => basename($path),
                    'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
                    'data' => $data,
                ];
            } catch (Throwable $exception) {
                Log::warning('Yandex storage attachment read failed', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $attachments;
    }

    private function addressesToRecipients(array $addresses): array
    {
        return collect($addresses)
            ->map(fn ($address) => [
                'address' => $address,
                'name' => null,
            ])
            ->values()
            ->all();
    }

    private function prepareJsonColumnsForModel(MailMessage $mailMessage, array $payload): array
    {
        foreach (['to', 'cc'] as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }

            if (!is_array($payload[$column])) {
                continue;
            }

            if ($mailMessage->hasCast($column, ['array', 'json', 'object', 'collection'])) {
                continue;
            }

            $payload[$column] = json_encode($payload[$column], JSON_UNESCAPED_UNICODE);
        }

        return $payload;
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

        if (!$value) {
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

    private function generateLocalMessageId(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (!$host) {
            $host = 'local.pischeprom';
        }

        return Str::uuid()->toString() . '@' . $host;
    }

    private function defaultFromAddress(): ?string
    {
        return config('mail.from.address')
            ?: env('MAIL_FROM_ADDRESS')
                ?: env('YANDEX_MAIL_USERNAME')
                    ?: env('YANDEX_IMAP_USERNAME')
                        ?: 'office@180022.ru';
    }

    private function defaultFromName(): ?string
    {
        return config('mail.from.name')
            ?: env('MAIL_FROM_NAME')
                ?: config('app.name');
    }
}
