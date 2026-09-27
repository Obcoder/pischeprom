<?php

namespace App\Services\Mail;

use App\Jobs\ArchiveSentMailJob;
use App\Models\AuthorizedMailDispatchAttempt;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email as MimeEmail;
use Throwable;

class AuthorizedMailDispatchService
{
    public const PERMISSION = 'mail.send';

    public const MAX_RECIPIENTS = 10;

    public const MAX_STORAGE_BYTES = 10_485_760;

    public const MAX_TOTAL_ATTACHMENT_BYTES = 20_971_520;

    private const STORAGE_MIMES = [
        'application/pdf', 'text/plain', 'text/csv', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png', 'image/jpeg',
    ];

    public function __construct(
        private readonly MailboxRegistry $mailboxes,
        private readonly MailOfferCatalog $offerCatalog,
        private readonly MailOfferRenderer $offerRenderer,
    ) {}

    /** @return array{duplicate: bool, mail_message: ?MailMessage} */
    public function dispatchMessage(
        User $actor,
        array $data,
        string $routeName,
        ?Unit $unit = null,
        ?Entity $entity = null,
    ): array {
        $this->authorize($actor, $unit);
        $body = (string) ($data['body'] ?? '');
        $html = nl2br(e($body));
        if (! empty($data['offer']) || (string) ($data['quoted_body'] ?? '') !== '') {
            $rendered = $this->offerRenderer->render(
                $body,
                $this->offerCatalog->resolve($data['offer']['items'] ?? []),
                $data['offer']['logistics'] ?? null,
                (string) ($data['quoted_body'] ?? ''),
            );
            $html = $rendered['html'];
            $body = $rendered['text'];
        }
        $recipients = $this->recipients($data);
        $attachments = $this->uploadedFiles($data['attachments'] ?? []);
        $storagePaths = $this->storagePaths($data['storage_files'] ?? [], $unit);
        $reply = $this->replyMessage($data['reply_to_mail_message_id'] ?? null, $unit);
        $headers = $this->replyHeaders($reply);
        $subject = trim((string) ($data['subject'] ?? '')) ?: '(без темы)';
        $requestedMailbox = $data['mailbox'] ?? $reply?->mailbox;
        $mailbox = $requestedMailbox ? $this->mailboxes->find((string) $requestedMailbox) : null;

        if ($requestedMailbox && ! $mailbox) {
            throw new MailDispatchException('mailbox_not_allowlisted', 'Выбранный почтовый ящик не настроен.');
        }

        $mailbox ??= $this->mailboxes->findOrDefault(null);
        $fromAddress = $this->safeAddress($mailbox['address'] ?? null);
        $fromName = $this->safeHeader((string) ($mailbox['from_name'] ?? config('mail.from.name', '')));

        if (! $fromAddress) {
            throw new MailDispatchException('sender_not_configured', 'Почтовый ящик отправителя не настроен.');
        }

        $storageAttachments = $this->loadStorageAttachments($storagePaths);
        $totalBytes = $attachments->sum(fn (UploadedFile $file) => (int) $file->getSize())
            + collect($storageAttachments)->sum('size');

        if ($totalBytes > self::MAX_TOTAL_ATTACHMENT_BYTES) {
            throw new MailDispatchException('attachment_total_too_large', 'Общий размер вложений превышает лимит.');
        }

        // Fallible read-only preparation precedes the ownership claim, so missing replies
        // or unavailable storage cannot leave a permanently claimed, unsent request.
        $attempt = $this->claimAttempt(
            actor: $actor,
            unit: $unit,
            routeName: $routeName,
            idempotencyKey: (string) $data['idempotency_key'],
            requestHash: $this->requestHash($data, $recipients, $storagePaths),
            recipientCount: count($recipients['all']),
            attachmentCount: $attachments->count() + count($storagePaths),
        );
        if (! $attempt->wasRecentlyCreated) {
            return [
                'duplicate' => true,
                'mail_message' => $attempt->mail_message_id ? MailMessage::find($attempt->mail_message_id) : null,
                'warning' => match ($attempt->status) {
                    'dispatched' => null,
                    'failed' => 'Предыдущая попытка завершилась до отправки. Письмо не отправлено.',
                    default => 'Результат предыдущей попытки отправки ещё не подтверждён. Проверьте почту перед повторной отправкой.',
                },
            ];
        }

        $mailMessage = null;
        $smtpStarted = false;
        $messageId = $attempt->public_id.'@'.Str::after($fromAddress, '@');
        try {
            $archive = app(SentMailArchive::class);
            $mailerName = $this->mailboxes->registerMailer($mailbox);
            $mime = (new MimeEmail)->html($html)->date(now()->toDateTimeImmutable());
            $mime->getHeaders()->addIdHeader('Message-ID', $messageId);
            $message = new Message($mime);
            $message->to($recipients['to']);
            if ($recipients['cc'] !== []) {
                $message->cc($recipients['cc']);
            }
            if ($recipients['bcc'] !== []) {
                $message->bcc($recipients['bcc']);
            }
            $message->from($fromAddress, $fromName);
            $message->replyTo($fromAddress, $fromName);
            $message->subject($subject);
            $message->text($body);

            if ($headers !== []) {
                $symfonyHeaders = $message->getHeaders();
                $symfonyHeaders->addTextHeader('In-Reply-To', $headers['in_reply_to']);
                $symfonyHeaders->addTextHeader('References', $headers['references']);
            }

            foreach ($attachments as $file) {
                $message->attach($file->getRealPath(), [
                    'as' => $this->safeFileName($file->getClientOriginalName()),
                    'mime' => $file->getMimeType(),
                ]);
            }

            foreach ($storageAttachments as $attachment) {
                $message->attachData($attachment['data'], $attachment['name'], ['mime' => $attachment['mime']]);
            }
            $mailMessage = $this->recordMessage(
                $fromAddress, $fromName, $recipients, $subject, $body, $html, $reply, $headers,
                $attachments->isNotEmpty() || $storageAttachments !== [], $entity, $unit,
                '<'.$messageId.'>', $mailbox['imap']['sent'] ?? 'Sent',
            );
            $attempt->forceFill(['mail_message_id' => $mailMessage->id])->save();
            // A durable, private original is required BEFORE SMTP can accept the message.
            $archive->prepare($mailMessage, $mime);
            $this->recordAttachments($mailMessage, $attachments, $storageAttachments);
            $transport = Mail::mailer($mailerName)->getSymfonyTransport();
            $mailMessage->forceFill(['delivery_status' => 'sending'])->save();
            $smtpStarted = true;
            // Use the transport receipt directly: a failing after-send listener must never
            // turn an accepted SMTP delivery into a failure inviting another send.
            $receipt = $transport->send($mime);
            if (! $receipt) {
                throw new \RuntimeException('Transport did not return an acceptance receipt.');
            }
        } catch (Throwable $exception) {
            try {
                $mailMessage?->forceFill(['delivery_status' => $smtpStarted ? 'unknown' : 'failed'])->save();
                $attempt->forceFill(['status' => $smtpStarted ? 'unknown' : 'failed', 'safe_error_code' => $smtpStarted ? 'transport_uncertain' : 'preparation_failed'])->save();
            } catch (Throwable) {
                // Preserve the original uncertainty even if persistence is unavailable.
            }
            throw new MailDispatchException(
                $smtpStarted ? 'transport_uncertain' : 'preparation_failed',
                $smtpStarted ? 'Не удалось подтвердить отправку. Проверьте почту перед повторной отправкой.' : 'Не удалось сохранить письмо перед отправкой. Письмо не отправлено.',
                502,
            );
        }

        // Everything below occurs AFTER SMTP acceptance and must not report "not sent".
        $warning = null;
        try {
            DB::transaction(function () use ($mailMessage, $attempt): void {
                $mailMessage->forceFill(['delivery_status' => 'sent', 'smtp_accepted_at' => now()])->save();
                $attempt->forceFill(['status' => 'dispatched', 'dispatched_at' => now(), 'safe_error_code' => null])->save();
            });
        } catch (Throwable) {
            return ['duplicate' => false, 'mail_message' => $mailMessage, 'warning' => 'SMTP принял письмо, но подтверждение не удалось сохранить. Не отправляйте письмо повторно.'];
        }

        try {
            $archive->archive($mailMessage);
        } catch (Throwable $exception) {
            $warning = 'Письмо отправлено. Копия в папке «Отправленные» ожидает синхронизации.';
            try {
                $mailMessage->forceFill([
                    'sent_copy_status' => $exception instanceof SentCopyPendingException ? 'pending' : 'failed',
                    'sent_copy_error' => 'sent_copy_unavailable',
                ])->save();
                if (filled($mailbox['imap']['host'] ?? null)) {
                    ArchiveSentMailJob::dispatch($mailMessage->id)->delay(now()->addSeconds(15));
                }
            } catch (Throwable) {
                // The persisted sent/pending row can be retried with mail:reconcile-sent.
            }
        }

        return ['duplicate' => false, 'mail_message' => $mailMessage, 'warning' => $warning];
    }

    public function authorize(User $actor, ?Unit $unit = null): void
    {
        if (($actor->status ?? 'active') !== 'active' || ! $actor->hasVerifiedEmail()) {
            throw new AuthorizationException('Manual mail dispatch is not authorized.');
        }

        try {
            $allowed = $actor->hasRole('admin', 'crm') || $actor->hasPermissionTo(self::PERMISSION, 'crm');
        } catch (Throwable) {
            $allowed = false;
        }

        if (! $allowed) {
            throw new AuthorizationException('Manual mail dispatch is not authorized.');
        }

        if ($unit) {
            Gate::forUser($actor)->authorize('sendMail', $unit);
        }
    }

    private function claimAttempt(
        User $actor,
        ?Unit $unit,
        string $routeName,
        string $idempotencyKey,
        string $requestHash,
        int $recipientCount,
        int $attachmentCount,
    ): AuthorizedMailDispatchAttempt {
        $keyHash = hash('sha256', $actor->id.'|'.$routeName.'|'.Str::lower($idempotencyKey));

        // firstOrCreate handles the UNIQUE-key race. Only its creator owns this send,
        // including while the persisted status is still claimed or sending.
        $attempt = AuthorizedMailDispatchAttempt::query()->firstOrCreate(
            ['idempotency_key_hash' => $keyHash], [
                'public_id' => (string) Str::uuid(),
                'user_id' => $actor->id,
                'unit_id' => $unit?->id,
                'route_name' => $routeName,
                'request_hash' => $requestHash,
                'recipient_count' => $recipientCount,
                'attachment_count' => $attachmentCount,
                'status' => 'claimed',
            ]);
        if (! hash_equals($attempt->request_hash, $requestHash)) {
            throw new MailDispatchException('idempotency_conflict', 'Ключ повторной отправки уже использован.', 409);
        }

        return $attempt;
    }

    private function recipients(array $data): array
    {
        $sets = [];
        foreach (['to', 'cc', 'bcc'] as $field) {
            $sets[$field] = collect($data[$field] ?? [])->map(fn ($value) => $this->safeAddress($value))->filter()->unique()->values()->all();
        }
        $all = collect($sets)->flatten()->unique()->values()->all();

        if ($sets['to'] === [] || count($all) > self::MAX_RECIPIENTS) {
            throw new MailDispatchException('invalid_recipients', 'Получатели письма не прошли проверку.');
        }

        return [...$sets, 'all' => $all];
    }

    private function safeAddress(mixed $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return ! preg_match('/[\r\n]/', $value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function safeHeader(string $value): string
    {
        return Str::limit(trim((string) preg_replace('/[\r\n]+/', ' ', $value)), 255, '');
    }

    private function uploadedFiles(mixed $files): Collection
    {
        $files = $files instanceof UploadedFile ? [$files] : (is_array($files) ? $files : []);

        return collect($files)->flatten()->filter(fn ($file) => $file instanceof UploadedFile)->values();
    }

    private function storagePaths(mixed $paths, ?Unit $unit): array
    {
        $paths = is_array($paths) ? $paths : [];
        $prefixes = ['mail/'];

        if ($unit) {
            $prefixes[] = 'units/'.$unit->id.'/';
            $prefixes[] = 'units/'.$unit->name.'/';
        }

        return collect($paths)->map(function ($value) use ($prefixes): string {
            $path = trim(str_replace('\\', '/', (string) $value), '/');
            $valid = $path !== '' && ! str_contains($path, '..') && ! str_contains($path, '//')
                && ! preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
                && collect($prefixes)->contains(fn (string $prefix) => Str::startsWith($path, $prefix));

            if (! $valid) {
                throw new MailDispatchException('storage_path_not_allowed', 'Путь вложения не разрешён.');
            }

            return $path;
        })->unique()->values()->all();
    }

    private function loadStorageAttachments(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $disk = Storage::disk('yandex');

        return collect($paths)->map(function (string $path) use ($disk): array {
            if (! $disk->exists($path)) {
                throw new MailDispatchException('storage_attachment_missing', 'Вложение не найдено.');
            }
            $size = (int) $disk->size($path);
            $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');
            if ($size < 0 || $size > self::MAX_STORAGE_BYTES || ! in_array($mime, self::STORAGE_MIMES, true)) {
                throw new MailDispatchException('storage_attachment_rejected', 'Вложение не прошло проверку.');
            }
            $contents = $disk->get($path);
            if (! is_string($contents)) {
                throw new MailDispatchException('storage_attachment_unreadable', 'Вложение недоступно.');
            }

            return ['disk' => 'yandex', 'path' => $path, 'name' => $this->safeFileName(basename($path)), 'mime' => $mime, 'size' => $size, 'data' => $contents];
        })->values()->all();
    }

    private function requestHash(array $data, array $recipients, array $storagePaths): string
    {
        // Preserve hashes of ordinary pre-offer requests, including retries across a deployment.
        $offerContent = [];
        if (! empty($data['offer'])) {
            $offerContent['offer'] = $data['offer'];
        }
        if ((string) ($data['quoted_body'] ?? '') !== '') {
            $offerContent['quoted_body_hash'] = hash('sha256', (string) $data['quoted_body']);
        }

        return hash('sha256', json_encode([
            'subject' => (string) ($data['subject'] ?? ''),
            'body_hash' => hash('sha256', (string) ($data['body'] ?? '')),
            'mailbox' => (string) ($data['mailbox'] ?? ''),
            'recipients' => $recipients,
            'storage_paths' => $storagePaths,
            'uploaded' => $this->uploadedFiles($data['attachments'] ?? [])->map(fn (UploadedFile $file) => [
                'name' => $this->safeFileName($file->getClientOriginalName()), 'size' => $file->getSize(), 'mime' => $file->getMimeType(),
            ])->all(),
            'reply' => $data['reply_to_mail_message_id'] ?? null,
            ...$offerContent,
        ], JSON_THROW_ON_ERROR));
    }

    private function replyMessage(mixed $id, ?Unit $unit): ?MailMessage
    {
        if (! $id) {
            return null;
        }
        $query = MailMessage::query()->whereKey((int) $id);
        if ($unit) {
            $query->whereHas('emails', function ($emailQuery) use ($unit): void {
                $emailQuery->whereHas('units', fn ($q) => $q->whereKey($unit->id))
                    ->orWhereHas('entities.units', fn ($q) => $q->whereKey($unit->id));
            });
        }
        $message = $query->first();
        if (! $message) {
            throw new MailDispatchException('reply_message_not_authorized', 'Исходное письмо недоступно.', 404);
        }

        return $message;
    }

    private function replyHeaders(?MailMessage $message): array
    {
        $id = trim((string) $message?->message_id);
        if ($id === '' || preg_match('/[\r\n]/', $id)) {
            return [];
        }
        $id = str_starts_with($id, '<') ? $id : '<'.$id.'>';
        if (! preg_match('/^<[^<>\s]+@[^<>\s]+>$/', $id)) {
            return [];
        }

        return ['in_reply_to' => $id, 'references' => $id];
    }

    private function recordMessage(
        string $fromAddress, string $fromName, array $recipients, string $subject, string $body,
        string $html, ?MailMessage $reply, array $headers, bool $hasAttachments, ?Entity $entity, ?Unit $unit,
        string $messageId, string $sentFolder,
    ): MailMessage {
        return DB::transaction(function () use (
            $fromAddress, $fromName, $recipients, $subject, $body, $html, $reply, $headers, $hasAttachments, $entity, $unit, $messageId, $sentFolder
        ): MailMessage {
            $message = MailMessage::query()->create([
                'mailbox' => $fromAddress, 'folder' => $sentFolder, 'direction' => 'outgoing',
                'message_id' => $messageId, 'delivery_status' => 'prepared', 'sent_copy_status' => 'pending',
                'reply_to_mail_message_id' => $reply?->id,
                'in_reply_to' => $headers['in_reply_to'] ?? null, 'references' => $headers['references'] ?? null,
                'subject' => $subject, 'message_date' => now(), 'from_address' => $fromAddress,
                'from_name' => $fromName, 'to' => $this->recipientPayload($recipients['to']),
                'cc' => $this->recipientPayload($recipients['cc']), 'preview' => Str::limit(trim(strip_tags($body)), 250),
                'html' => $html, 'text' => $body, 'body_loaded_at' => now(), 'has_attachments' => $hasAttachments,
            ]);
            $sender = Email::query()->where('address', $fromAddress)->first();
            if ($sender) {
                $message->emails()->syncWithoutDetaching([$sender->id => ['role' => 'from']]);
            }
            foreach ($recipients['all'] as $address) {
                $email = Email::withTrashed()->firstOrCreate(['address' => $address], ['source' => 'manual_mail', 'is_active' => true, 'last_seen_at' => now()]);
                if ($email->trashed()) {
                    $email->restore();
                }
                $email->forceFill(['last_seen_at' => now(), 'source' => $email->source ?: 'manual_mail'])->save();
                $message->emails()->syncWithoutDetaching([$email->id => ['role' => 'to']]);
                if ($entity) {
                    $email->entities()->syncWithoutDetaching([$entity->id]);
                }
                if ($unit) {
                    $email->units()->syncWithoutDetaching([$unit->id]);
                }
            }

            return $message;
        });
    }

    private function recordAttachments(MailMessage $message, Collection $uploads, array $storageAttachments): void
    {
        foreach ($storageAttachments as $attachment) {
            MailMessageAttachment::query()->create([
                'mail_message_id' => $message->id, 'disk' => $attachment['disk'], 'path' => $attachment['path'],
                'original_name' => $attachment['name'], 'file_name' => $attachment['name'], 'mime_type' => $attachment['mime'],
                'size' => $attachment['size'], 'disposition' => 'attachment', 'saved_to_disk_at' => now(),
            ]);
        }
        if ($uploads->isEmpty()) {
            return;
        }
        $diskName = (string) (config('services.yandex_mail.attachments_disk', 'yandex') ?: 'yandex');
        $disk = Storage::disk($diskName);
        foreach ($uploads as $file) {
            try {
                $name = $this->safeFileName($file->getClientOriginalName());
                $path = 'mail/outgoing/'.$message->id.'/'.Str::uuid().'-'.$name;
                $disk->put($path, file_get_contents($file->getRealPath()), ['ContentType' => $file->getMimeType()]);
                MailMessageAttachment::query()->create([
                    'mail_message_id' => $message->id, 'disk' => $diskName, 'path' => $path,
                    'original_name' => $name, 'file_name' => basename($path), 'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(), 'disposition' => 'attachment', 'saved_to_disk_at' => now(),
                ]);
            } catch (Throwable $exception) {
                Log::warning('Authorized mail attachment archive failed', ['mail_message_id' => $message->id, 'exception_type' => $exception::class]);
            }
        }
    }

    private function recipientPayload(array $addresses): array
    {
        return collect($addresses)->map(fn ($address) => ['address' => $address, 'name' => null])->all();
    }

    private function safeFileName(string $name): string
    {
        $name = trim((string) preg_replace('/[^\pL\pN._ -]+/u', '_', basename(str_replace('\\', '/', $name))));

        return Str::limit($name !== '' ? $name : 'attachment.bin', 180, '');
    }
}
