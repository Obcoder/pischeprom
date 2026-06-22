<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Models\MailMessageNote;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MailMessageActionController extends Controller
{
    public function send(Request $request, MailboxRegistry $mailboxes): JsonResponse
    {
        $request->validate([
            'subject' => ['nullable', 'string', 'max:998'],
            'body' => ['nullable', 'string'],
            'mailbox' => ['nullable', 'email'],
            'to' => ['required'],
            'cc' => ['nullable'],
            'bcc' => ['nullable'],
            'attachments' => ['nullable'],
            'attachments.*' => ['file', 'max:51200'],
            'storage_files' => ['nullable'],
            'storage_files.*' => ['nullable'],
            'reply_to_mail_message_id' => ['nullable', 'integer', 'exists:mail_messages,id'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $to = $this->normalizeRecipients($request->input('to'));
        $cc = $this->normalizeRecipients($request->input('cc'));
        $bcc = $this->normalizeRecipients($request->input('bcc'));

        if (empty($to)) {
            return response()->json([
                'message' => 'Не указан получатель письма.',
            ], 422);
        }

        $replyToMessage = $request->input('reply_to_mail_message_id')
            ? MailMessage::query()->find((int) $request->input('reply_to_mail_message_id'))
            : null;

        $replyHeaders = $this->replyHeaders($replyToMessage);
        $subject = trim((string) $request->input('subject')) ?: '(без темы)';
        $body = (string) $request->input('body', '');
        $bodyHtml = nl2br(e($body));
        $requestedMailbox = $request->input('mailbox') ?: $replyToMessage?->mailbox;
        $mailbox = $requestedMailbox ? $mailboxes->find($requestedMailbox) : null;

        if ($requestedMailbox && !$mailbox) {
            return response()->json([
                'message' => 'Выбранный почтовый ящик не настроен.',
            ], 422);
        }

        $mailbox = $mailbox ?: $mailboxes->findOrDefault(null);
        $fromAddress = $mailbox['address'];
        $fromName = $mailbox['from_name'] ?: $this->defaultFromName();
        $mailerName = $mailboxes->registerMailer($mailbox);
        $localAttachments = $this->normalizeUploadedFiles($request->file('attachments'));
        $storageFiles = $this->normalizeStorageFiles($request->input('storage_files'));
        $storageAttachments = $this->buildStorageAttachments($storageFiles);

        try {
            Mail::mailer($mailerName)->html($bodyHtml, function ($message) use (
                $to,
                $cc,
                $bcc,
                $subject,
                $fromAddress,
                $fromName,
                $localAttachments,
                $storageAttachments,
                $replyHeaders
            ) {
                $message->to($to);

                if (! empty($cc)) {
                    $message->cc($cc);
                }

                if (! empty($bcc)) {
                    $message->bcc($bcc);
                }

                if ($fromAddress) {
                    $message->from($fromAddress, $fromName);
                }

                $message->subject($subject);

                if (! empty($replyHeaders)) {
                    $headers = $message->getHeaders();

                    if (! empty($replyHeaders['in_reply_to'])) {
                        $headers->addTextHeader('In-Reply-To', $replyHeaders['in_reply_to']);
                    }

                    if (! empty($replyHeaders['references'])) {
                        $headers->addTextHeader('References', $replyHeaders['references']);
                    }
                }

                foreach ($localAttachments as $file) {
                    $message->attach($file->getRealPath(), [
                        'as' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    ]);
                }

                foreach ($storageAttachments as $attachment) {
                    $message->attachData($attachment['data'], $attachment['name'], [
                        'mime' => $attachment['mime'],
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Письмо не отправлено.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        $mailMessage = $this->recordOutgoingMessage(
            fromAddress: $fromAddress,
            fromName: $fromName,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            subject: $subject,
            body: $body,
            bodyHtml: $bodyHtml,
            replyToMessage: $replyToMessage,
            replyHeaders: $replyHeaders,
            hasAttachments: $localAttachments->isNotEmpty() || ! empty($storageAttachments),
            entityId: $request->integer('entity_id') ?: null,
            unitId: $request->integer('unit_id') ?: null,
        );

        $this->storeOutgoingLocalAttachments($mailMessage, $localAttachments);
        $this->recordStorageAttachments($mailMessage, $storageAttachments);

        return response()->json([
            'message' => 'Письмо отправлено.',
            'mail_message' => $this->messagePayload($mailMessage->fresh()),
        ]);
    }

    public function syncAttachments(
        Request $request,
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        $mailMessage = $service->syncAttachments(
            mailMessage: $mailMessage,
            force: $request->boolean('force', true),
        );

        return response()->json($this->messagePayload($mailMessage), 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function saveAttachment(
        Request $request,
        MailMessage $mailMessage,
        int $index,
        YandexMailboxService $service,
    ): JsonResponse {
        $data = $request->validate([
            'folder' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $mailMessage = $service->saveAttachment(
                mailMessage: $mailMessage,
                index: $index,
                folder: $data['folder'] ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось сохранить вложение в Yandex S3: ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json($this->messagePayload($mailMessage), 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function attachmentFolders(
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        return response()->json([
            'folders' => $service->attachmentFolders($mailMessage),
        ]);
    }

    public function storeAttachmentFolder(
        Request $request,
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        $data = $request->validate([
            'folder' => ['required', 'string', 'max:500'],
        ]);

        return response()->json([
            'folder' => $service->createAttachmentFolder($data['folder'], $mailMessage),
            'folders' => $service->attachmentFolders($mailMessage),
        ], 201);
    }

    public function storeNote(Request $request, MailMessage $mailMessage): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'importance' => ['nullable', Rule::in(['normal', 'important', 'critical'])],
        ]);

        MailMessageNote::query()->create([
            'mail_message_id' => $mailMessage->id,
            'user_id' => $request->user()?->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'importance' => $data['importance'] ?? 'important',
        ]);

        return response()->json($this->messagePayload($mailMessage->fresh()));
    }

    public function createLead(Request $request, MailMessage $mailMessage): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $leadPayload = [
            'source' => 'email',
            'status' => Lead::STATUS_OPEN,
            'title' => ($data['title'] ?? null) ?: ($mailMessage->subject ?: 'Лид из письма'),
            'description' => ($data['description'] ?? null) ?: $this->leadDescription($mailMessage),
            'entity_id' => $data['entity_id'] ?? $this->firstRelatedEntityId($mailMessage),
            'unit_id' => $data['unit_id'] ?? $this->firstRelatedUnitId($mailMessage),
            'last_activity_at' => $mailMessage->message_date ?: now(),
        ];

        if (Schema::hasColumn('leads', 'mail_message_id')) {
            $leadPayload['mail_message_id'] = $mailMessage->id;
        }

        $lead = Lead::query()->create($leadPayload);

        return response()->json([
            'lead' => $lead->fresh(['entity', 'unit']),
            'mail_message' => $this->messagePayload($mailMessage->fresh()),
        ], 201);
    }

    private function recordOutgoingMessage(
        ?string $fromAddress,
        ?string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $body,
        string $bodyHtml,
        ?MailMessage $replyToMessage,
        array $replyHeaders,
        bool $hasAttachments,
        ?int $entityId,
        ?int $unitId,
    ): MailMessage {
        return DB::transaction(function () use (
            $fromAddress,
            $fromName,
            $to,
            $cc,
            $bcc,
            $subject,
            $body,
            $bodyHtml,
            $replyToMessage,
            $replyHeaders,
            $hasAttachments,
            $entityId,
            $unitId,
        ) {
            $mailMessage = MailMessage::query()->create([
                'mailbox' => $fromAddress,
                'folder' => 'Sent',
                'direction' => 'outgoing',
                'imap_uid' => null,
                'message_id' => $this->generateLocalMessageId(),
                'reply_to_mail_message_id' => $replyToMessage?->id,
                'in_reply_to' => $replyHeaders['in_reply_to'] ?? null,
                'references' => $replyHeaders['references'] ?? null,
                'subject' => $subject,
                'message_date' => now(),
                'from_address' => $fromAddress,
                'from_name' => $fromName,
                'to' => $this->addressesToRecipients($to),
                'cc' => $this->addressesToRecipients($cc),
                'preview' => Str::limit(trim(strip_tags($body)), 250),
                'html' => $bodyHtml,
                'text' => $body,
                'body_loaded_at' => now(),
                'has_attachments' => $hasAttachments,
            ]);

            if ($fromAddress) {
                $senderId = Email::query()->where('address', Str::lower($fromAddress))->value('id');

                if ($senderId) {
                    $this->attachEmailToMailMessage($mailMessage->id, (int) $senderId, 'from');
                }
            }

            $recipientAddresses = collect([$to, $cc, $bcc])->flatten()->filter()->unique()->values();

            foreach ($recipientAddresses as $address) {
                $email = $this->findOrCreateEmail((string) $address);
                $this->attachEmailToMailMessage($mailMessage->id, $email->id, 'to');

                if ($entityId) {
                    $email->entities()->syncWithoutDetaching([$entityId]);
                }

                if ($unitId) {
                    $email->units()->syncWithoutDetaching([$unitId]);
                }
            }

            return $mailMessage;
        });
    }

    private function storeOutgoingLocalAttachments(MailMessage $mailMessage, Collection $files): void
    {
        if ($files->isEmpty()) {
            return;
        }

        $diskName = config('services.yandex_mail.attachments_disk', 'yandex') ?: 'yandex';
        $disk = Storage::disk($diskName);

        foreach ($files as $file) {
            $safeName = $this->safeFileName($file->getClientOriginalName());
            $path = sprintf('mail/outgoing/%d/%s-%s', $mailMessage->id, Str::uuid()->toString(), $safeName);

            try {
                $disk->put($path, file_get_contents($file->getRealPath()), [
                    'ContentType' => $file->getMimeType() ?: 'application/octet-stream',
                ]);

                MailMessageAttachment::query()->create([
                    'mail_message_id' => $mailMessage->id,
                    'disk' => $diskName,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_name' => basename($path),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'disposition' => 'attachment',
                    'saved_to_disk_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function recordStorageAttachments(MailMessage $mailMessage, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            MailMessageAttachment::query()->updateOrCreate(
                [
                    'mail_message_id' => $mailMessage->id,
                    'disk' => $attachment['disk'],
                    'path' => $attachment['path'],
                ],
                [
                    'original_name' => $attachment['name'],
                    'file_name' => basename($attachment['path']),
                    'mime_type' => $attachment['mime'],
                    'size' => $attachment['size'],
                    'disposition' => 'attachment',
                    'saved_to_disk_at' => now(),
                ]
            );
        }
    }

    private function normalizeRecipients(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : preg_split('/[,;\s]+/', $value);
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn ($item) => is_array($item) ? ($item['address'] ?? $item['email'] ?? null) : (is_object($item) ? ($item->address ?? $item->email ?? null) : $item))
            ->map(fn ($address) => Str::lower(trim((string) $address)))
            ->filter(fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeUploadedFiles(mixed $files): Collection
    {
        if (! $files) {
            return collect();
        }

        if ($files instanceof UploadedFile) {
            return collect([$files]);
        }

        if (! is_array($files)) {
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
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn ($item) => is_array($item) ? ($item['path'] ?? $item['key'] ?? null) : (is_object($item) ? ($item->path ?? $item->key ?? null) : $item))
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
        $diskName = 'yandex';
        $disk = Storage::disk($diskName);

        return collect($storageFiles)
            ->map(function (string $path) use ($disk, $diskName) {
                if (! $disk->exists($path)) {
                    return null;
                }

                $data = $disk->get($path);

                if ($data === null || $data === false) {
                    return null;
                }

                return [
                    'disk' => $diskName,
                    'path' => $path,
                    'name' => basename($path),
                    'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
                    'size' => $disk->size($path),
                    'data' => $data,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function attachEmailToMailMessage(int $mailMessageId, int $emailId, string $role): void
    {
        DB::table('email_mail_message')->updateOrInsert(
            [
                'email_id' => $emailId,
                'mail_message_id' => $mailMessageId,
                'role' => $role,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function findOrCreateEmail(string $address): Email
    {
        $email = Email::withTrashed()->firstOrCreate(
            ['address' => Str::lower(trim($address))],
            [
                'source' => 'mail_message',
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        if ($email->trashed()) {
            $email->restore();
        }

        $email->forceFill(['last_seen_at' => now()])->save();

        return $email;
    }

    private function replyHeaders(?MailMessage $message): array
    {
        if (! $message?->message_id) {
            return [];
        }

        $inReplyTo = $this->normalizeMessageId($message->message_id);
        $references = collect([
            ...$this->messageIdsFromReferences($message->references),
            ...$this->messageIdsFromReferences($message->raw_headers),
            $inReplyTo,
        ])->filter()->unique()->implode(' ');

        return [
            'in_reply_to' => $inReplyTo,
            'references' => $references ?: $inReplyTo,
        ];
    }

    private function messageIdsFromReferences(?string $value): array
    {
        if (! $value) {
            return [];
        }

        if (preg_match('/^References:\s*(.+)$/mi', $value, $matches)) {
            $value = $matches[1];
        }

        preg_match_all('/<[^<>\s]+@[^<>\s]+>/', $value, $matches);

        return $matches[0] ?? [];
    }

    private function normalizeMessageId(string $messageId): string
    {
        $messageId = trim($messageId);

        return $messageId === '' || str_starts_with($messageId, '<')
            ? $messageId
            : "<{$messageId}>";
    }

    private function addressesToRecipients(array $addresses): array
    {
        return collect($addresses)
            ->map(fn ($address) => ['address' => $address, 'name' => null])
            ->values()
            ->all();
    }

    private function generateLocalMessageId(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'local.pischeprom';

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
        return config('mail.from.name') ?: env('MAIL_FROM_NAME') ?: config('app.name');
    }

    private function leadDescription(MailMessage $mailMessage): string
    {
        $body = trim($mailMessage->text ?: strip_tags((string) $mailMessage->html) ?: $mailMessage->preview ?: '');

        return trim(implode("\n\n", array_filter([
            'Источник: email',
            'Письмо #' . $mailMessage->id,
            'From: ' . trim(($mailMessage->from_name ? $mailMessage->from_name . ' ' : '') . (string) $mailMessage->from_address),
            'Subject: ' . (string) $mailMessage->subject,
            Str::limit($body, 2000),
        ])));
    }

    private function firstRelatedEntityId(MailMessage $mailMessage): ?int
    {
        return $mailMessage
            ->loadMissing('emails.entities')
            ->emails
            ->flatMap(fn (Email $email) => $email->entities)
            ->pluck('id')
            ->filter()
            ->first();
    }

    private function firstRelatedUnitId(MailMessage $mailMessage): ?int
    {
        return $mailMessage
            ->loadMissing(['emails.units', 'emails.entities.units'])
            ->emails
            ->flatMap(function (Email $email) {
                return $email->units->merge(
                    $email->entities->flatMap(fn ($entity) => $entity->units)
                );
            })
            ->pluck('id')
            ->filter()
            ->first();
    }

    private function safeFileName(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '-', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: '';
        $name = preg_replace('/\s+/', ' ', $name) ?: '';

        return $name !== '' ? $name : 'attachment.bin';
    }

    private function messagePayload(MailMessage $mailMessage): MailMessage
    {
        return $mailMessage->load([
            'attachments',
            'notes.user:id,name',
            'leads:id,mail_message_id,title,status,entity_id,unit_id',
            'emails:id,address,name',
            'emails.units:id,name',
            'emails.entities:id,name',
        ]);
    }
}
