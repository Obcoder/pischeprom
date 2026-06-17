<?php

namespace App\Services\Mail;

use App\Models\Email;
use App\Models\MailMessageAttachment;
use App\Models\MailMessage;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionObject;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

class YandexMailboxService
{
    public function syncAll(int $limit = 1000): array
    {
        $folders = config('services.yandex_mail.folders', [
            config('services.yandex_mail.imap.inbox', 'INBOX'),
            config('services.yandex_mail.imap.sent', 'Sent'),
        ]);

        $result = [];

        foreach ($folders as $folder) {
            try {
                $result[$folder] = $this->syncFolder(
                    folderName: $folder,
                    direction: null,
                    limit: $limit,
                );
            } catch (Throwable $exception) {
                logger()->error('Yandex mailbox folder sync failed', [
                    'folder' => $folder,
                    'limit' => $limit,
                    'message' => $exception->getMessage(),
                ]);

                $result[$folder] = 0;
            }
        }

        return $result;
    }

    public function syncFolder(string $folderName, ?string $direction = null, int $limit = 1000): int
    {
        $client = $this->client();

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $folderName);

            if (!$folder) {
                logger()->warning('Yandex IMAP folder not found', [
                    'requested_folder' => $folderName,
                    'direction' => $direction,
                    'available_folders' => collect($client->getFolders())->map(fn ($folder) => [
                        'path' => $folder->path ?? null,
                        'name' => $folder->name ?? null,
                    ])->values()->all(),
                ]);

                return 0;
            }

            logger()->info('Yandex folder opened', [
                'folder' => $folderName,
                'direction' => $direction,
            ]);

            $total = $folder->query()->all()->count();

            logger()->info('Yandex folder count', [
                'folder' => $folderName,
                'direction' => $direction,
                'total' => $total,
                'limit' => $limit,
            ]);

            if ($total === 0) {
                return 0;
            }

            $pageSize = 10;
            $maxToFetch = min($total, $limit);
            $pages = (int) ceil($maxToFetch / $pageSize);

            $stored = 0;

            for ($page = 1; $page <= $pages; $page++) {
                $query = $folder
                    ->query()
                    ->all()
                    ->leaveUnread()
                    ->setFetchBody(false)
                    ->softFail()
                    ->setFetchOrderDesc();

                if (method_exists($query, 'setFetchAttachment')) {
                    $query->setFetchAttachment(false);
                }

                $messages = $query
                    ->limit($pageSize, $page)
                    ->get();

                foreach ($messages as $message) {
                    if ($stored >= $limit) {
                        break 2;
                    }

                    $this->storeMessage(
                        message: $message,
                        folderName: $folderName,
                        direction: $direction,
                    );

                    $stored++;
                }
            }

            logger()->info('Yandex folder synced', [
                'folder' => $folderName,
                'direction' => $direction,
                'stored' => $stored,
            ]);

            return $stored;
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'sync_folder',
                'folder' => $folderName,
            ]);
        }
    }

    protected function client()
    {
        $manager = new ClientManager();

        return $manager->make([
                                  'host' => config('services.yandex_mail.imap.host'),
                                  'port' => config('services.yandex_mail.imap.port', 993),
                                  'encryption' => config('services.yandex_mail.imap.encryption', 'ssl'),
                                  'validate_cert' => true,
                                  'username' => config('services.yandex_mail.imap.username'),
                                  'password' => config('services.yandex_mail.imap.password'),
                                  'protocol' => 'imap',
                              ]);
    }

    protected function storeMessage($message, string $folderName, ?string $direction = null): void
    {
        $from = Arr::first($this->addresses($this->attribute($message, 'from')));
        $to = $this->addresses($this->attribute($message, 'to'));
        $cc = $this->addresses($this->attribute($message, 'cc'));

        $direction = $direction ?: $this->detectDirection($from);

        $messageId = $this->stringValue(
            $this->firstAttributeValue(
                $this->attribute($message, 'message_id')
            )
        );

        $subject = $this->decodeMime(
            $this->stringValue(
                $this->firstAttributeValue(
                    $this->attribute($message, 'subject')
                )
            )
        );

        $messageDate = $this->dateValue(
            $this->firstAttributeValue(
                $this->attribute($message, 'date')
            )
        );

        $imapUid = $this->uid($message);

        if (!$imapUid) {
            $imapUid = $this->fallbackUid(
                folderName: $folderName,
                direction: $direction,
                messageId: $messageId,
                subject: $subject,
                messageDate: $messageDate?->toDateTimeString(),
                from: $from,
                to: $to,
                cc: $cc,
            );
        }

        $identity = $messageId
            ? [
                'mailbox' => config('services.yandex_mail.address'),
                'message_id' => $messageId,
            ]
            : [
                'mailbox' => config('services.yandex_mail.address'),
                'folder' => $folderName,
                'imap_uid' => $imapUid,
            ];

        $mailbox = config('services.yandex_mail.address');

        $mailMessage = MailMessage::query()->firstOrNew([
                                                            'mailbox' => $mailbox,
                                                            'folder' => $folderName,
                                                            'imap_uid' => $imapUid,
                                                        ]);

        $mailMessage->fill([
                               'message_id' => $messageId,
                               'direction' => $direction,
                               'subject' => $subject,
                               'message_date' => $messageDate,
                               'from_address' => $from['address'] ?? null,
                               'from_name' => $from['name'] ?? null,
                               'to' => $to,
                               'cc' => $cc,
                               'preview' => $mailMessage->preview,
                               'has_attachments' => $mailMessage->has_attachments ?? false,
                               'raw_headers' => $message->header->raw ?? null,
                           ]);

        $mailMessage->save();

        if ($direction === 'incoming' && !empty($from['address'])) {
            $email = $this->findOrCreateEmail(
                address: $from['address'],
                name: $from['name'] ?? null,
            );

            $this->attachRole($mailMessage, $email, 'from');
        }

        if ($direction === 'outgoing') {
            foreach ($to as $address) {
                $email = $this->findOrCreateEmail(
                    address: $address['address'],
                    name: $address['name'] ?? null,
                );

                $this->attachRole($mailMessage, $email, 'to');
            }

            foreach ($cc as $address) {
                $email = $this->findOrCreateEmail(
                    address: $address['address'],
                    name: $address['name'] ?? null,
                );

                $this->attachRole($mailMessage, $email, 'cc');
            }
        }
    }

    protected function detectDirection(?array $from): string
    {
        $ownAddress = Str::lower((string) config('services.yandex_mail.address'));
        $fromAddress = Str::lower((string) ($from['address'] ?? ''));

        return $fromAddress === $ownAddress
            ? 'outgoing'
            : 'incoming';
    }

    protected function findOrCreateEmail(string $address, ?string $name = null): Email
    {
        $address = Str::lower(trim($address));

        $email = Email::withTrashed()->firstOrCreate(
            ['address' => $address],
            [
                'name' => $name,
                'source' => 'yandex',
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        if ($email->trashed()) {
            $email->restore();
        }

        $email->forceFill([
                              'last_seen_at' => now(),
                              'name' => $email->name ?: $name,
                              'source' => $email->source ?: 'yandex',
                          ])->save();

        return $email;
    }

    protected function attachRole(MailMessage $mailMessage, Email $email, string $role): void
    {
        DB::table('email_mail_message')->updateOrInsert(
            [
                'email_id' => $email->id,
                'mail_message_id' => $mailMessage->id,
                'role' => $role,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    protected function uid($message): ?int
    {
        foreach (['uid', 'msgno', 'message_no'] as $property) {
            try {
                $value = is_object($message)
                    ? $message->{$property}
                    : null;
            } catch (\Throwable) {
                $value = null;
            }

            $value = $this->firstAttributeValue($value) ?? $value;

            if (is_numeric($value)) {
                return (int) $value;
            }

            $string = $this->stringValue($value);

            if (is_numeric($string)) {
                return (int) $string;
            }
        }

        foreach (['getUid', 'getUID'] as $method) {
            if (is_object($message) && method_exists($message, $method)) {
                try {
                    $value = $message->{$method}();
                } catch (\Throwable) {
                    $value = null;
                }

                $value = $this->firstAttributeValue($value) ?? $value;

                if (is_numeric($value)) {
                    return (int) $value;
                }

                $string = $this->stringValue($value);

                if (is_numeric($string)) {
                    return (int) $string;
                }
            }
        }

        return null;
    }

    protected function fallbackUid(
        string $folderName,
        string $direction,
        ?string $messageId,
        ?string $subject,
        ?string $messageDate,
        ?array $from,
        array $to,
        array $cc,
    ): int {
        $fingerprint = json_encode([
                                       'folder' => $folderName,
                                       'direction' => $direction,
                                       'message_id' => $messageId,
                                       'subject' => $subject,
                                       'message_date' => $messageDate,
                                       'from' => $from,
                                       'to' => $to,
                                       'cc' => $cc,
                                   ], JSON_UNESCAPED_UNICODE);

        return (int) sprintf('%u', crc32($fingerprint));
    }

    protected function preview($message): ?string
    {
        $text = $this->stringValue($this->call($message, 'getTextBody'));
        $html = $this->stringValue($this->call($message, 'getHTMLBody'));

        $plain = trim(strip_tags($text ?: $html ?: ''));

        return $plain ? Str::limit($plain, 500) : null;
    }

    protected function call($object, string $method)
    {
        if (!is_object($object) || !method_exists($object, $method)) {
            return null;
        }

        return $object->{$method}();
    }

    protected function stringValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return trim($value) ?: null;
        }

        if (is_scalar($value)) {
            return trim((string) $value) ?: null;
        }

        if (is_object($value) && method_exists($value, 'toString')) {
            return trim($value->toString()) ?: null;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return trim((string) $value) ?: null;
        }

        return null;
    }

    protected function dateValue($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        $date = $this->stringValue($value);

        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }

    protected function decodeMime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $decoded = @iconv_mime_decode(
            $value,
            ICONV_MIME_DECODE_CONTINUE_ON_ERROR,
            'UTF-8'
        );

        return trim($decoded ?: $value) ?: null;
    }

    protected function attribute($message, string $name)
    {
        return is_object($message)
            ? ($message->{$name} ?? null)
            : null;
    }

    protected function firstAttributeValue($attribute)
    {
        return Arr::first($this->attributeValues($attribute));
    }

    protected function attributeValues($attribute): array
    {
        if (!$attribute) {
            return [];
        }

        if (is_array($attribute)) {
            return $attribute;
        }

        if ($attribute instanceof DateTimeInterface) {
            return [$attribute];
        }

        if (is_string($attribute) || is_numeric($attribute) || is_bool($attribute)) {
            return [$attribute];
        }

        foreach (['all', 'toArray', 'get'] as $method) {
            if (is_object($attribute) && method_exists($attribute, $method)) {
                $value = $attribute->{$method}();

                if (is_array($value)) {
                    return $value;
                }

                if ($value !== null) {
                    return [$value];
                }
            }
        }

        if (is_object($attribute)) {
            try {
                $reflection = new ReflectionObject($attribute);

                if ($reflection->hasProperty('values')) {
                    $property = $reflection->getProperty('values');
                    $property->setAccessible(true);

                    $values = $property->getValue($attribute);

                    return is_array($values) ? $values : [$values];
                }
            } catch (Throwable) {
                return [];
            }
        }

        return [];
    }

    protected function addresses($attribute): array
    {
        $items = $this->attributeValues($attribute);

        if (empty($items)) {
            return [];
        }

        $addresses = [];

        foreach ($items as $item) {
            $address = null;
            $name = null;

            if (is_array($item)) {
                $address = $item['mail']
                    ?? $item['email']
                    ?? $item['address']
                    ?? null;

                $name = $item['personal']
                    ?? $item['name']
                    ?? null;

                if (!$address && !empty($item['mailbox']) && !empty($item['host'])) {
                    $address = $item['mailbox'] . '@' . $item['host'];
                }
            }

            if (is_object($item)) {
                $address = $item->mail
                    ?? $item->email
                    ?? $item->address
                    ?? null;

                $name = $item->personal
                    ?? $item->name
                    ?? null;

                if (!$address && !empty($item->mailbox) && !empty($item->host)) {
                    $address = $item->mailbox . '@' . $item->host;
                }

                if (!$address && !empty($item->full)) {
                    $address = $this->emailFromString($item->full);
                }
            }

            if (!$address && is_string($item)) {
                $address = $this->emailFromString($item);
            }

            if (!$address || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $addresses[] = [
                'address' => Str::lower(trim($address)),
                'name' => $this->decodeMime($this->stringValue($name)),
            ];
        }

        return collect($addresses)
            ->unique('address')
            ->values()
            ->all();
    }

    protected function emailFromString(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
            return Str::lower($matches[0]);
        }

        return null;
    }

    protected function resolveFolder($client, string $folderName)
    {
        $folder = $client->getFolder($folderName);

        if ($folder) {
            return $folder;
        }

        $folders = $client->getFolders();

        foreach ($folders as $availableFolder) {
            $path = $availableFolder->path ?? null;
            $name = $availableFolder->name ?? null;

            if ($path === $folderName || $name === $folderName) {
                return $availableFolder;
            }
        }

        $needle = mb_strtolower($folderName);

        foreach ($folders as $availableFolder) {
            $path = mb_strtolower((string) ($availableFolder->path ?? ''));
            $name = mb_strtolower((string) ($availableFolder->name ?? ''));

            if ($path === $needle || $name === $needle) {
                return $availableFolder;
            }
        }

        logger()->warning('Yandex IMAP folder not resolved', [
            'requested_folder' => $folderName,
            'available_folders' => collect($folders)->map(fn ($folder) => [
                'path' => $folder->path ?? null,
                'name' => $folder->name ?? null,
            ])->values()->all(),
        ]);

        return null;
    }

    public function loadBody(
        MailMessage $mailMessage,
        bool $force = false,
        bool $withAttachments = false,
        bool $includeAttachmentList = true,
    ): MailMessage
    {
        $needsAttachmentList = $includeAttachmentList && ($mailMessage->has_attachments || $mailMessage->attachments()->exists());

        if (
            !$force
            && $mailMessage->body_loaded_at
            && !$withAttachments
            && !$needsAttachmentList
        ) {
            return $this->withAvailableAttachments(
                $mailMessage->loadMissing(['attachments', 'notes.user', 'leads'])
            );
        }

        if (!$mailMessage->imap_uid) {
            return $this->withAvailableAttachments(
                $mailMessage->loadMissing(['attachments', 'notes.user', 'leads'])
            );
        }

        $client = $this->client();

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $mailMessage->folder);

            if (!$folder) {
                logger()->warning('Yandex load body folder not found', [
                    'mail_message_id' => $mailMessage->id,
                    'folder' => $mailMessage->folder,
                    'imap_uid' => $mailMessage->imap_uid,
                ]);

                return $this->withAvailableAttachments(
                    $mailMessage->loadMissing(['attachments', 'notes.user', 'leads'])
                );
            }

            $query = $folder
                ->query()
                ->leaveUnread()
                ->setFetchBody(true);

            if (($withAttachments || $includeAttachmentList) && method_exists($query, 'setFetchAttachment')) {
                $query->setFetchAttachment(true);
            }

            $message = $query->getMessageByUid((int) $mailMessage->imap_uid);

            if (!$message) {
                logger()->warning('Yandex message body not found by uid', [
                    'mail_message_id' => $mailMessage->id,
                    'folder' => $mailMessage->folder,
                    'imap_uid' => $mailMessage->imap_uid,
                    'subject' => $mailMessage->subject,
                ]);

                return $this->withAvailableAttachments(
                    $mailMessage->loadMissing(['attachments', 'notes.user', 'leads'])
                );
            }

            $html = $this->messageBody($message, 'html');
            $text = $this->messageBody($message, 'text');

            if (!$text && $html) {
                $text = trim(strip_tags($html));
            }

            $payload = [
                'html' => $html,
                'text' => $text,
                'body_loaded_at' => now(),
            ];

            if ($withAttachments) {
                $storedAttachments = $this->storeAttachments($mailMessage, $message);

                if ($storedAttachments > 0 || $this->messageHasAttachments($message)) {
                    $payload['has_attachments'] = true;
                }
            } elseif ($includeAttachmentList && $this->messageHasAttachments($message)) {
                $payload['has_attachments'] = true;
            }

            $mailMessage->forceFill($payload)->save();

            $fresh = $mailMessage->fresh()->load(['attachments', 'notes.user', 'leads']);

            if ($includeAttachmentList) {
                $fresh->setAttribute('available_attachments', $this->attachmentPayloads($fresh, $message));
            }

            return $this->withAvailableAttachments($fresh);
        } catch (Throwable $exception) {
            logger()->error('Yandex load body failed', [
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
                'imap_uid' => $mailMessage->imap_uid,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'load_body',
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
            ]);
        }
    }

    public function syncAttachments(MailMessage $mailMessage, bool $force = false): MailMessage
    {
        return $this->loadBody(
            mailMessage: $mailMessage,
            force: $force,
            withAttachments: true,
        );
    }

    public function attachmentFolders(?MailMessage $mailMessage = null): array
    {
        $diskName = $this->attachmentsDiskName();
        $disk = Storage::disk($diskName);
        $root = $this->attachmentRootPath();

        $paths = collect([
            $root,
            $mailMessage ? $this->defaultAttachmentFolder($mailMessage) : null,
        ])
            ->merge($this->storedAttachmentFolders($diskName))
            ->merge($this->storageAttachmentFolders($disk, $root))
            ->filter()
            ->map(fn ($path) => trim((string) $path, '/'))
            ->filter(fn ($path) => $path !== '' && ($path === $root || Str::startsWith($path, $root . '/')))
            ->unique()
            ->sort()
            ->values();

        return $paths
            ->map(fn ($path) => $this->attachmentFolderPayload($path, $diskName))
            ->all();
    }

    public function createAttachmentFolder(string $folder, ?MailMessage $mailMessage = null): array
    {
        $diskName = $this->attachmentsDiskName();
        $disk = Storage::disk($diskName);
        $path = $this->normalizeAttachmentFolder($folder, $mailMessage);

        abort_if($path === $this->attachmentRootPath(), 422, 'Укажите папку внутри mail/attachments.');

        $created = $disk->put("{$path}/.keep", 'created: ' . now()->toIso8601String());

        if ($created === false) {
            throw new RuntimeException('Папка не создана в Yandex S3.');
        }

        return $this->attachmentFolderPayload($path, $diskName);
    }

    public function saveAttachment(MailMessage $mailMessage, int $index, ?string $folder = null): MailMessage
    {
        if (!$mailMessage->imap_uid) {
            return $this->withAvailableAttachments(
                $mailMessage->loadMissing(['attachments', 'notes.user', 'leads'])
            );
        }

        $client = $this->client();
        $diskName = $this->attachmentsDiskName();
        $targetFolder = $this->normalizeAttachmentFolder($folder, $mailMessage);

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $mailMessage->folder);

            if (!$folder) {
                logger()->warning('Yandex save attachment folder not found', [
                    'mail_message_id' => $mailMessage->id,
                    'folder' => $mailMessage->folder,
                    'imap_uid' => $mailMessage->imap_uid,
                ]);

                return $mailMessage;
            }

            $query = $folder
                ->query()
                ->leaveUnread()
                ->setFetchBody(true);

            if (method_exists($query, 'setFetchAttachment')) {
                $query->setFetchAttachment(true);
            }

            $message = $query->getMessageByUid((int) $mailMessage->imap_uid);
            $attachments = $message ? $this->messageAttachments($message) : [];
            $attachment = $attachments[$index] ?? null;

            if (!$attachment) {
                return $this->withAvailableAttachments(
                    $mailMessage->fresh()->load(['attachments', 'notes.user', 'leads'])
                );
            }

            $savedAttachment = $this->storeAttachment(
                mailMessage: $mailMessage,
                attachment: $attachment,
                index: $index,
                diskName: $diskName,
                disk: Storage::disk($diskName),
                folderPath: $targetFolder,
            );
            $mailMessage->forceFill(['has_attachments' => true])->save();

            $fresh = $mailMessage->fresh()->load(['attachments', 'notes.user', 'leads']);
            $fresh->setAttribute('available_attachments', $this->attachmentPayloads($fresh, $message));
            $fresh->setAttribute('saved_attachment', $savedAttachment
                ? $this->savedAttachmentPayload($savedAttachment->fresh(), $index)
                : null);
            $fresh->setAttribute('saved_folder', $this->attachmentFolderPayload($targetFolder, $diskName));

            return $fresh;
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'save_attachment',
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
                'attachment_index' => $index,
            ]);
        }
    }

    protected function storeAttachments(MailMessage $mailMessage, $message): int
    {
        $attachments = $this->messageAttachments($message);

        if (empty($attachments)) {
            return 0;
        }

        $diskName = $this->attachmentsDiskName();
        $disk = Storage::disk($diskName);
        $stored = 0;

        foreach ($attachments as $index => $attachment) {
            try {
                if ($this->storeAttachment($mailMessage, $attachment, $index, $diskName, $disk)) {
                    $stored++;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $stored;
    }

    protected function storeAttachment(
        MailMessage $mailMessage,
        $attachment,
        int $index,
        ?string $diskName = null,
        $disk = null,
        ?string $folderPath = null,
    ): ?MailMessageAttachment
    {
        $diskName = $diskName ?: $this->attachmentsDiskName();
        $disk = $disk ?: Storage::disk($diskName);
        $folderPath = $folderPath ?: $this->defaultAttachmentFolder($mailMessage);
        $originalName = $this->attachmentName($attachment, $index);
        $content = $this->attachmentContent($attachment);

        if ($content === null || $content === '') {
            logger()->warning('Yandex mail attachment skipped: empty content', [
                'mail_message_id' => $mailMessage->id,
                'original_name' => $originalName,
            ]);

            return null;
        }

        $size = strlen($content);
        $mimeType = $this->attachmentMimeType($attachment);
        $existing = MailMessageAttachment::query()
            ->where('mail_message_id', $mailMessage->id)
            ->where('original_name', $originalName)
            ->where('size', $size)
            ->first();

        if (
            $existing
            && $existing->path
            && dirname($existing->path) === $folderPath
            && $disk->exists($existing->path)
        ) {
            return $existing;
        }

        $safeName = $this->safeAttachmentName($originalName);
        $path = sprintf(
            '%s/%s-%s',
            $folderPath,
            Str::uuid()->toString(),
            $safeName
        );

        try {
            $stored = $disk->put($path, $content, [
                'ContentType' => $mimeType,
            ]);

            if ($stored === false) {
                throw new RuntimeException('S3 put returned false.');
            }

            $saved = MailMessageAttachment::query()->updateOrCreate(
                [
                    'mail_message_id' => $mailMessage->id,
                    'original_name' => $originalName,
                    'size' => $size,
                ],
                [
                    'disk' => $diskName,
                    'path' => $path,
                    'file_name' => basename($path),
                    'mime_type' => $mimeType,
                    'content_id' => $this->attachmentContentId($attachment),
                    'disposition' => $this->attachmentDisposition($attachment),
                    'saved_to_disk_at' => now(),
                ]
            );

            if ($existing && $existing->path && $existing->path !== $path && $disk->exists($existing->path)) {
                $disk->delete($existing->path);
            }

            return $saved;
        } catch (Throwable $exception) {
            logger()->error('Yandex mail attachment save failed', [
                'mail_message_id' => $mailMessage->id,
                'original_name' => $originalName,
                'path' => $path,
                'disk' => $diskName,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function withAvailableAttachments(MailMessage $mailMessage): MailMessage
    {
        if ($mailMessage->getAttribute('available_attachments') !== null) {
            return $mailMessage;
        }

        $mailMessage->loadMissing('attachments');
        $mailMessage->setAttribute(
            'available_attachments',
            $mailMessage->attachments->map(fn (MailMessageAttachment $attachment, int $index) => [
                'id' => $attachment->id,
                'index' => $index,
                'original_name' => $attachment->original_name,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'content_id' => $attachment->content_id,
                'disposition' => $attachment->disposition,
                'is_image' => $this->isImageAttachment($attachment->mime_type, $attachment->original_name ?: $attachment->file_name),
                'is_saved' => true,
                'disk' => $attachment->disk,
                'path' => $attachment->path,
                'folder_path' => $attachment->folder_path,
                'folder_url' => $attachment->folder_url,
                'url' => $attachment->url,
                'preview_url' => $this->isImageAttachment($attachment->mime_type, $attachment->original_name ?: $attachment->file_name)
                    ? $attachment->url
                    : null,
                'saved_to_disk_at' => $attachment->saved_to_disk_at,
            ])->values()->all()
        );

        return $mailMessage;
    }

    protected function attachmentPayloads(MailMessage $mailMessage, $message): array
    {
        $mailMessage->loadMissing('attachments');

        return collect($this->messageAttachments($message))
            ->map(function ($attachment, int $index) use ($mailMessage) {
                $name = $this->attachmentName($attachment, $index);
                $content = $this->attachmentContent($attachment);
                $size = is_string($content) ? strlen($content) : null;
                $mimeType = $this->attachmentMimeType($attachment);
                $saved = $this->matchingSavedAttachment($mailMessage, $name, $size);
                $isImage = $this->isImageAttachment($mimeType, $name);

                return [
                    'id' => $saved?->id,
                    'index' => $index,
                    'original_name' => $name,
                    'file_name' => $saved?->file_name ?: $name,
                    'mime_type' => $mimeType,
                    'size' => $size ?? $saved?->size,
                    'content_id' => $this->attachmentContentId($attachment),
                    'disposition' => $this->attachmentDisposition($attachment),
                    'is_image' => $isImage,
                    'is_saved' => (bool) $saved,
                    'disk' => $saved?->disk,
                    'path' => $saved?->path,
                    'folder_path' => $saved?->folder_path,
                    'folder_url' => $saved?->folder_url,
                    'url' => $saved?->url,
                    'preview_url' => $this->attachmentPreviewUrl($content, $mimeType, $isImage, $saved),
                    'saved_to_disk_at' => $saved?->saved_to_disk_at,
                ];
            })
            ->values()
            ->all();
    }

    protected function savedAttachmentPayload(?MailMessageAttachment $attachment, int $index): ?array
    {
        if (!$attachment) {
            return null;
        }

        return [
            'id' => $attachment->id,
            'index' => $index,
            'original_name' => $attachment->original_name,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'content_id' => $attachment->content_id,
            'disposition' => $attachment->disposition,
            'is_image' => $this->isImageAttachment($attachment->mime_type, $attachment->original_name ?: $attachment->file_name),
            'is_saved' => true,
            'disk' => $attachment->disk,
            'path' => $attachment->path,
            'folder_path' => $attachment->folder_path,
            'folder_url' => $attachment->folder_url,
            'url' => $attachment->url,
            'preview_url' => $this->isImageAttachment($attachment->mime_type, $attachment->original_name ?: $attachment->file_name)
                ? $attachment->url
                : null,
            'saved_to_disk_at' => $attachment->saved_to_disk_at,
        ];
    }

    protected function attachmentsDiskName(): string
    {
        return config('services.yandex_mail.attachments_disk', 'yandex') ?: 'yandex';
    }

    protected function attachmentRootPath(): string
    {
        return 'mail/attachments';
    }

    protected function defaultAttachmentFolder(MailMessage $mailMessage): string
    {
        return $this->attachmentRootPath() . '/' . $mailMessage->id;
    }

    protected function normalizeAttachmentFolder(?string $folder, ?MailMessage $mailMessage = null): string
    {
        $folder = trim(str_replace('\\', '/', (string) $folder), '/');

        if ($folder === '') {
            return $mailMessage ? $this->defaultAttachmentFolder($mailMessage) : $this->attachmentRootPath();
        }

        abort_if(Str::contains($folder, ['..', '//']), 422, 'Invalid attachment folder.');
        abort_if((bool) preg_match('/[\x00-\x1F\x7F]/', $folder), 422, 'Invalid attachment folder.');

        $root = $this->attachmentRootPath();

        if ($folder !== $root && !Str::startsWith($folder, $root . '/')) {
            $folder = $root . '/' . $folder;
        }

        return trim($folder, '/');
    }

    protected function storedAttachmentFolders(string $diskName): array
    {
        return MailMessageAttachment::query()
            ->where('disk', $diskName)
            ->whereNotNull('path')
            ->pluck('path')
            ->map(fn ($path) => trim(dirname((string) $path), '/.'))
            ->filter()
            ->values()
            ->all();
    }

    protected function storageAttachmentFolders($disk, string $root): array
    {
        try {
            if (method_exists($disk, 'allDirectories')) {
                return $disk->allDirectories($root);
            }

            return $this->collectStorageDirectories($disk, $root);
        } catch (Throwable $exception) {
            logger()->warning('Yandex attachment folders list failed', [
                'root' => $root,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    protected function collectStorageDirectories($disk, string $path, int $depth = 0): array
    {
        if ($depth > 6) {
            return [];
        }

        $directories = $disk->directories($path);

        return collect($directories)
            ->flatMap(fn ($directory) => [
                $directory,
                ...$this->collectStorageDirectories($disk, $directory, $depth + 1),
            ])
            ->values()
            ->all();
    }

    protected function attachmentFolderPayload(string $path, string $diskName): array
    {
        $path = trim($path, '/');
        $root = $this->attachmentRootPath();
        $relative = Str::startsWith($path, $root . '/')
            ? Str::after($path, $root . '/')
            : ($path === $root ? '' : $path);

        return [
            'name' => $relative !== '' ? basename($path) : 'attachments',
            'path' => $path,
            'relative_path' => $relative,
            'url' => $this->attachmentFolderUrl($path, $diskName),
        ];
    }

    protected function attachmentFolderUrl(string $path, string $diskName): ?string
    {
        try {
            return Storage::disk($diskName)->url(rtrim($path, '/') . '/');
        } catch (Throwable) {
            return null;
        }
    }

    protected function matchingSavedAttachment(MailMessage $mailMessage, string $name, ?int $size): ?MailMessageAttachment
    {
        return $mailMessage->attachments
            ->first(function (MailMessageAttachment $attachment) use ($name, $size) {
                return $attachment->original_name === $name
                    && ($size === null || (int) $attachment->size === $size);
            });
    }

    protected function attachmentPreviewUrl(?string $content, string $mimeType, bool $isImage, ?MailMessageAttachment $saved): ?string
    {
        if ($saved?->url && $isImage) {
            return $saved->url;
        }

        if (!$isImage || !is_string($content) || $content === '') {
            return null;
        }

        if (strlen($content) > 5 * 1024 * 1024) {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($content));
    }

    protected function isImageAttachment(?string $mimeType, ?string $name = null): bool
    {
        if ($mimeType && str_starts_with(strtolower($mimeType), 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', (string) $name);
    }

    protected function messageAttachments($message): array
    {
        foreach (['getAttachments', 'attachments'] as $methodOrProperty) {
            try {
                $value = null;

                if (is_object($message) && method_exists($message, $methodOrProperty)) {
                    $value = $message->{$methodOrProperty}();
                } elseif (is_object($message) && isset($message->{$methodOrProperty})) {
                    $value = $message->{$methodOrProperty};
                }

                if ($value instanceof \Traversable) {
                    return iterator_to_array($value);
                }

                if (is_array($value)) {
                    return $value;
                }

                if (is_object($value) && method_exists($value, 'all')) {
                    $all = $value->all();

                    return is_array($all) ? $all : [];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    protected function messageHasAttachments($message): bool
    {
        if (! is_object($message)) {
            return false;
        }

        foreach (['hasAttachments', 'has_attachments'] as $methodOrProperty) {
            try {
                if (method_exists($message, $methodOrProperty)) {
                    return (bool) $message->{$methodOrProperty}();
                }

                if (isset($message->{$methodOrProperty})) {
                    return (bool) $message->{$methodOrProperty};
                }
            } catch (Throwable) {
                continue;
            }
        }

        return ! empty($this->messageAttachments($message));
    }

    protected function attachmentName($attachment, int $index): string
    {
        foreach (['getName', 'getFilename', 'getFileName'] as $method) {
            if (is_object($attachment) && method_exists($attachment, $method)) {
                $name = $this->decodeMime($this->stringValue($attachment->{$method}()));

                if ($name) {
                    return $name;
                }
            }
        }

        foreach (['name', 'filename', 'file_name'] as $property) {
            $name = is_object($attachment) ? ($attachment->{$property} ?? null) : ($attachment[$property] ?? null);
            $name = $this->decodeMime($this->stringValue($name));

            if ($name) {
                return $name;
            }
        }

        return 'attachment-' . ($index + 1) . '.bin';
    }

    protected function attachmentContent($attachment): ?string
    {
        foreach (['getContent', 'getData', 'getBody'] as $method) {
            if (is_object($attachment) && method_exists($attachment, $method)) {
                $content = $attachment->{$method}();

                if (is_string($content)) {
                    return $content;
                }
            }
        }

        foreach (['content', 'data', 'body'] as $property) {
            $content = is_object($attachment) ? ($attachment->{$property} ?? null) : ($attachment[$property] ?? null);

            if (is_string($content)) {
                return $content;
            }
        }

        return null;
    }

    protected function attachmentMimeType($attachment): string
    {
        foreach (['getMimeType', 'getContentType'] as $method) {
            if (is_object($attachment) && method_exists($attachment, $method)) {
                $mime = $this->stringValue($attachment->{$method}());

                if ($mime) {
                    return $mime;
                }
            }
        }

        foreach (['mime_type', 'mime', 'content_type'] as $property) {
            $mime = is_object($attachment) ? ($attachment->{$property} ?? null) : ($attachment[$property] ?? null);
            $mime = $this->stringValue($mime);

            if ($mime) {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    protected function attachmentContentId($attachment): ?string
    {
        foreach (['getContentId', 'getId'] as $method) {
            if (is_object($attachment) && method_exists($attachment, $method)) {
                return $this->stringValue($attachment->{$method}());
            }
        }

        return is_object($attachment)
            ? $this->stringValue($attachment->content_id ?? $attachment->id ?? null)
            : $this->stringValue($attachment['content_id'] ?? $attachment['id'] ?? null);
    }

    protected function attachmentDisposition($attachment): ?string
    {
        foreach (['getDisposition'] as $method) {
            if (is_object($attachment) && method_exists($attachment, $method)) {
                return $this->stringValue($attachment->{$method}());
            }
        }

        return is_object($attachment)
            ? $this->stringValue($attachment->disposition ?? null)
            : $this->stringValue($attachment['disposition'] ?? null);
    }

    protected function safeAttachmentName(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '-', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: '';
        $name = preg_replace('/\s+/', ' ', $name) ?: '';

        return $name !== '' ? $name : 'attachment.bin';
    }

    protected function safeDisconnect($client, array $context = []): void
    {
        try {
            $client->disconnect();
        } catch (Throwable $exception) {
            logger()->warning('Yandex IMAP disconnect failed', [
                ...$context,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function messageBody($message, string $type): ?string
    {
        foreach ([
                     'getHTMLBody',
                     'getHtmlBody',
                     'getTextBody',
                     'getPlainBody',
                 ] as $method) {
            if (
                is_object($message)
                && method_exists($message, $method)
                && (
                    ($type === 'html' && str_contains(strtolower($method), 'html'))
                    || ($type === 'text' && !str_contains(strtolower($method), 'html'))
                )
            ) {
                $value = $message->{$method}();

                if ($body = $this->stringValue($value)) {
                    return $body;
                }
            }
        }

        $vars = is_object($message) ? get_object_vars($message) : [];
        $bodies = $vars['bodies'] ?? [];

        if (is_array($bodies)) {
            $value = $bodies[$type] ?? null;

            if ($body = $this->stringValue($value)) {
                return $body;
            }
        }

        return null;
    }
}
