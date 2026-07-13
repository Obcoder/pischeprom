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
    public function __construct(private readonly MailboxRegistry $mailboxes)
    {
    }

    public function mailboxes(): array
    {
        return $this->mailboxes->publicMailboxes();
    }

    public function syncAll(int $limit = 1000, ?string $mailboxAddress = null): array
    {
        if ($mailboxAddress) {
            $mailbox = $this->mailboxes->find($mailboxAddress);

            if (!$mailbox) {
                throw new RuntimeException('Почтовый ящик не настроен: ' . $mailboxAddress);
            }

            $configuredMailboxes = [$mailbox];
        } else {
            $configuredMailboxes = $this->mailboxes->all();
        }

        $result = [];

        foreach ($configuredMailboxes as $mailbox) {
            foreach ($this->mailboxes->folders($mailbox) as $folder) {
                $key = $mailbox['address'] . '/' . $folder;

                try {
                    $result[$key] = $this->syncFolder(
                        folderName: $folder,
                        direction: null,
                        limit: $limit,
                        mailbox: $mailbox,
                    );
                } catch (Throwable $exception) {
                    logger()->error('Yandex mailbox folder sync failed', [
                        'mailbox' => $mailbox['address'],
                        'folder' => $folder,
                        'limit' => $limit,
                        'message' => $exception->getMessage(),
                    ]);

                    $result[$key] = 0;
                }
            }
        }

        return $result;
    }

    public function syncFolder(
        string $folderName,
        ?string $direction = null,
        int $limit = 1000,
        array|string|null $mailbox = null,
    ): int
    {
        if (is_array($mailbox)) {
            $mailbox = $mailbox;
        } elseif (is_string($mailbox) && trim($mailbox) !== '') {
            $mailboxAddress = $mailbox;
            $mailbox = $this->mailboxes->find($mailboxAddress);

            if (!$mailbox) {
                throw new RuntimeException('Почтовый ящик не настроен: ' . $mailboxAddress);
            }
        } else {
            $mailbox = $this->mailboxes->findOrDefault(null);
        }

        $client = $this->client($mailbox);

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $folderName);

            if (!$folder) {
                logger()->warning('Yandex IMAP folder not found', [
                    'mailbox' => $mailbox['address'],
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
                'mailbox' => $mailbox['address'],
                'folder' => $folderName,
                'direction' => $direction,
            ]);

            $total = $folder->query()->all()->count();

            logger()->info('Yandex folder count', [
                'mailbox' => $mailbox['address'],
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
                        mailbox: $mailbox,
                    );

                    $stored++;
                }
            }

            logger()->info('Yandex folder synced', [
                'mailbox' => $mailbox['address'],
                'folder' => $folderName,
                'direction' => $direction,
                'stored' => $stored,
            ]);

            return $stored;
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'sync_folder',
                'mailbox' => $mailbox['address'],
                'folder' => $folderName,
            ]);
        }
    }

    protected function client(?array $mailbox = null)
    {
        $manager = new ClientManager();

        return $manager->make(
            $this->mailboxes->imapClientConfig($mailbox ?: $this->mailboxes->findOrDefault(null))
        );
    }

    protected function storeMessage($message, string $folderName, ?string $direction = null, ?array $mailbox = null): void
    {
        $mailbox = $mailbox ?: $this->mailboxes->findOrDefault(null);
        $mailboxAddress = $mailbox['address'];
        $from = Arr::first($this->addresses($this->attribute($message, 'from')));
        $to = $this->addresses($this->attribute($message, 'to'));
        $cc = $this->addresses($this->attribute($message, 'cc'));

        $direction = $direction ?: $this->detectDirection($from, $mailboxAddress);

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

        $mailMessage = MailMessage::query()->firstOrNew([
                                                            'mailbox' => $mailboxAddress,
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
                               'has_attachments' => (bool) ($mailMessage->has_attachments ?? false)
                                   || $this->messageHeadersSuggestAttachments($message),
                               'raw_headers' => $this->stringValue($message->header->raw ?? null),
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

    protected function detectDirection(?array $from, ?string $ownAddress = null): string
    {
        $defaultMailbox = $this->mailboxes->default();
        $ownAddress = Str::lower((string) ($ownAddress ?: ($defaultMailbox['address'] ?? '')));
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
            return $this->safeUtf8($value);
        }

        if (is_scalar($value)) {
            return $this->safeUtf8((string) $value);
        }

        if (is_object($value) && method_exists($value, 'toString')) {
            return $this->safeUtf8($value->toString());
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $this->safeUtf8((string) $value);
        }

        return null;
    }

    protected function safeUtf8(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1251', 'ISO-8859-1', 'UTF-8'] as $encoding) {
            if (!function_exists('mb_convert_encoding')) {
                continue;
            }

            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);

            if (is_string($converted) && (!function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8'))) {
                return trim($converted) ?: null;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($converted) && trim($converted) !== ''
            ? trim($converted)
            : null;
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

        return $this->safeUtf8($decoded ?: $value);
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
        $needsAttachmentList = $includeAttachmentList
            && (
                $mailMessage->has_attachments
                || $mailMessage->attachments()->exists()
                || $this->mailMessageHeadersSuggestAttachments($mailMessage)
            );

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

        $mailbox = $mailMessage->mailbox
            ? $this->mailboxes->find($mailMessage->mailbox)
            : null;

        if ($mailMessage->mailbox && !$mailbox) {
            throw new RuntimeException('Почтовый ящик не настроен: ' . $mailMessage->mailbox);
        }

        $mailbox = $mailbox ?: $this->mailboxes->findOrDefault(null);
        $client = $this->client($mailbox);

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $mailMessage->folder);

            if (!$folder) {
                logger()->warning('Yandex load body folder not found', [
                    'mailbox' => $mailbox['address'],
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
                    'mailbox' => $mailbox['address'],
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
            $availableAttachments = null;

            if ($includeAttachmentList || ($html && str_contains($html, 'cid:'))) {
                $availableAttachments = $this->attachmentPayloads($mailMessage->loadMissing('attachments'), $message);
                $html = $this->replaceCidImageSources($html, $availableAttachments);
            }

            if (!$text && $html) {
                $text = trim(strip_tags($html));
            }

            $payload = [
                'html' => $html,
                'text' => $text,
                'body_loaded_at' => now(),
            ];

            if (($withAttachments || $includeAttachmentList) && $this->messageHasAttachments($message)) {
                $payload['has_attachments'] = true;
            }

            $mailMessage->forceFill($payload)->save();

            $fresh = $mailMessage->fresh()->load(['attachments', 'notes.user', 'leads']);

            if ($includeAttachmentList) {
                $fresh->setAttribute('available_attachments', $availableAttachments ?? $this->attachmentPayloads($fresh, $message));
            }

            return $this->withAvailableAttachments($fresh);
        } catch (Throwable $exception) {
            logger()->error('Yandex load body failed', [
                'mailbox' => $mailbox['address'],
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
                'imap_uid' => $mailMessage->imap_uid,
                'message' => $exception->getMessage(),
            ]);

            return $this->withMailSyncError(
                $mailMessage->fresh() ?: $mailMessage,
                'Не удалось обновить письмо из Yandex IMAP. Показаны сохранённые данные.'
            );
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'load_body',
                'mailbox' => $mailbox['address'],
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
            withAttachments: false,
            includeAttachmentList: true,
        );
    }

    public function attachmentFolders(?MailMessage $mailMessage = null): array
    {
        $diskName = $this->attachmentsDiskName();

        try {
            $storageFolders = collect();

            foreach ($this->attachmentUploadDisks($diskName, Storage::disk($diskName)) as [$candidateDiskName, $candidateDisk]) {
                try {
                    $storageFolders = $storageFolders->merge(
                        collect($this->storageAttachmentFolders($candidateDisk, ''))
                            ->map(fn ($path) => [
                                'path' => trim((string) $path, '/'),
                                'disk' => $candidateDiskName,
                            ])
                    );
                } catch (Throwable $exception) {
                    logger()->warning('Yandex attachment folders disk unavailable', [
                        'disk' => $candidateDiskName,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $folders = collect([
                [
                    'path' => $this->attachmentRootPath(),
                    'disk' => $diskName,
                ],
                $mailMessage ? [
                    'path' => $this->defaultAttachmentFolder($mailMessage),
                    'disk' => $diskName,
                ] : null,
            ])
                ->merge($this->mailMessageUnitFolders($mailMessage, $diskName))
                ->merge($this->storedAttachmentFolders())
                ->merge($storageFolders)
                ->filter()
                ->map(fn (array $folder) => [
                    'path' => trim((string) ($folder['path'] ?? ''), '/'),
                    'disk' => (string) ($folder['disk'] ?? $diskName),
                ])
                ->filter(fn (array $folder) => $folder['path'] !== '')
                ->unique('path')
                ->sortBy('path', SORT_NATURAL)
                ->values();
        } catch (Throwable $exception) {
            logger()->error('Yandex attachment folders payload failed', [
                'disk' => $diskName,
                'mail_message_id' => $mailMessage?->id,
                'message' => $exception->getMessage(),
            ]);

            $folders = collect([
                [
                    'path' => $this->attachmentRootPath(),
                    'disk' => $diskName,
                ],
                $mailMessage ? [
                    'path' => $this->defaultAttachmentFolder($mailMessage),
                    'disk' => $diskName,
                ] : null,
            ])->filter()->values();
        }

        return $folders
            ->map(fn (array $folder) => $this->attachmentFolderPayload($folder['path'], $folder['disk'] ?: $diskName))
            ->all();
    }

    public function createAttachmentFolder(string $folder, ?MailMessage $mailMessage = null): array
    {
        $diskName = $this->attachmentsDiskName();
        $path = $this->normalizeAttachmentFolder($folder, $mailMessage);

        abort_if($path === '', 422, 'Укажите папку внутри bucket.');

        $createdDiskName = null;

        foreach ($this->attachmentUploadDisks($diskName, Storage::disk($diskName)) as [$candidateDiskName, $candidateDisk]) {
            if ($this->putAttachmentContent($candidateDisk, "{$path}/.keep", 'created: ' . now()->toIso8601String(), 'text/plain')) {
                $createdDiskName = $candidateDiskName;
                break;
            }
        }

        if ($createdDiskName === null) {
            throw new RuntimeException('Папка не создана в Yandex S3.');
        }

        return $this->attachmentFolderPayload($path, $createdDiskName);
    }

    public function saveAttachment(MailMessage $mailMessage, int $index, ?string $folder = null): MailMessage
    {
        if (!$mailMessage->imap_uid) {
            return $this->withAvailableAttachments(
                $mailMessage->loadMissing(['attachments', 'notes.user', 'leads'])
            );
        }

        $mailbox = $mailMessage->mailbox
            ? $this->mailboxes->find($mailMessage->mailbox)
            : null;

        if ($mailMessage->mailbox && !$mailbox) {
            throw new RuntimeException('Почтовый ящик не настроен: ' . $mailMessage->mailbox);
        }

        $mailbox = $mailbox ?: $this->mailboxes->findOrDefault(null);
        $client = $this->client($mailbox);
        $diskName = $this->attachmentsDiskName();
        $targetFolder = $this->normalizeAttachmentFolder($folder, $mailMessage);

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $mailMessage->folder);

            if (!$folder) {
                logger()->warning('Yandex save attachment folder not found', [
                    'mailbox' => $mailbox['address'],
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
            $attachments = $message ? array_values($this->messageAttachments($message)) : [];
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

            if (!$savedAttachment) {
                throw new RuntimeException('Не удалось прочитать содержимое вложения.');
            }

            $mailMessage->forceFill(['has_attachments' => true])->save();

            $fresh = $mailMessage->fresh()->load(['attachments', 'notes.user', 'leads']);
            $fresh->setAttribute('available_attachments', $this->attachmentPayloads($fresh, $message));
            $fresh->setAttribute('saved_attachment', $savedAttachment
                ? $this->savedAttachmentPayload($savedAttachment->fresh(), $index)
                : null);
            $fresh->setAttribute('saved_folder', $this->attachmentFolderPayload(
                $targetFolder,
                $savedAttachment?->disk ?: $diskName
            ));

            return $fresh;
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'save_attachment',
                'mailbox' => $mailbox['address'],
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
                'attachment_index' => $index,
            ]);
        }
    }

    public function downloadAttachment(MailMessage $mailMessage, int $index): ?array
    {
        if (!$mailMessage->imap_uid) {
            return null;
        }

        $mailbox = $mailMessage->mailbox
            ? $this->mailboxes->find($mailMessage->mailbox)
            : null;

        if ($mailMessage->mailbox && !$mailbox) {
            throw new RuntimeException('Почтовый ящик не настроен: ' . $mailMessage->mailbox);
        }

        $mailbox = $mailbox ?: $this->mailboxes->findOrDefault(null);
        $client = $this->client($mailbox);

        try {
            $client->connect();

            $folder = $this->resolveFolder($client, $mailMessage->folder);

            if (!$folder) {
                logger()->warning('Yandex download attachment folder not found', [
                    'mailbox' => $mailbox['address'],
                    'mail_message_id' => $mailMessage->id,
                    'folder' => $mailMessage->folder,
                    'imap_uid' => $mailMessage->imap_uid,
                ]);

                return null;
            }

            $query = $folder
                ->query()
                ->leaveUnread()
                ->setFetchBody(true);

            if (method_exists($query, 'setFetchAttachment')) {
                $query->setFetchAttachment(true);
            }

            $message = $query->getMessageByUid((int) $mailMessage->imap_uid);
            $attachment = $message ? (array_values($this->messageAttachments($message))[$index] ?? null) : null;

            if (!$attachment) {
                return null;
            }

            $content = $this->attachmentContent($attachment);

            if ($content === null) {
                throw new RuntimeException('Не удалось прочитать содержимое вложения.');
            }

            return [
                'name' => $this->attachmentName($attachment, $index),
                'mime_type' => $this->attachmentMimeType($attachment),
                'size' => strlen($content),
                'content' => $content,
            ];
        } finally {
            $this->safeDisconnect($client, [
                'operation' => 'download_attachment',
                'mailbox' => $mailbox['address'],
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
                'attachment_index' => $index,
            ]);
        }
    }

    protected function storeAttachments(MailMessage $mailMessage, $message): int
    {
        $attachments = array_values($this->messageAttachments($message));

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

        if ($existing && $existing->path && dirname($existing->path) === $folderPath) {
            try {
                if (Storage::disk($existing->disk ?: $diskName)->exists($existing->path)) {
                    return $existing;
                }
            } catch (Throwable) {
                // Continue with a fresh upload if the old disk is unavailable.
            }
        }

        $safeName = $this->safeAttachmentName($originalName);
        $path = sprintf(
            '%s/%s-%s',
            $folderPath,
            Str::uuid()->toString(),
            $safeName
        );

        try {
            $uploaded = null;

            foreach ($this->attachmentUploadDisks($diskName, $disk) as [$candidateDiskName, $candidateDisk]) {
                if ($this->putAttachmentContent($candidateDisk, $path, $content, $mimeType)) {
                    $uploaded = [$candidateDiskName, $candidateDisk];
                    break;
                }

                logger()->warning('Yandex mail attachment upload attempt rejected', [
                    'mail_message_id' => $mailMessage->id,
                    'original_name' => $originalName,
                    'path' => $path,
                    'disk' => $candidateDiskName,
                    'mime' => $mimeType,
                    'size' => $size,
                ]);
            }

            if ($uploaded === null) {
                throw new RuntimeException(sprintf(
                    'Yandex S3 rejected attachment upload: disks=%s path=%s mime=%s size=%d',
                    implode(',', collect($this->attachmentUploadDisks($diskName, $disk))->pluck(0)->all()),
                    $path,
                    $mimeType,
                    $size
                ));
            }

            [$storedDiskName] = $uploaded;

            $saved = MailMessageAttachment::query()->updateOrCreate(
                [
                    'mail_message_id' => $mailMessage->id,
                    'original_name' => $originalName,
                    'size' => $size,
                ],
                [
                    'disk' => $storedDiskName,
                    'path' => $path,
                    'file_name' => basename($path),
                    'mime_type' => $mimeType,
                    'content_id' => $this->attachmentContentId($attachment),
                    'disposition' => $this->attachmentDisposition($attachment),
                    'saved_to_disk_at' => now(),
                ]
            );

            if ($existing && $existing->path && $existing->path !== $path) {
                try {
                    $existingDisk = Storage::disk($existing->disk ?: $storedDiskName);

                    if ($existingDisk->exists($existing->path)) {
                        $existingDisk->delete($existing->path);
                    }
                } catch (Throwable) {
                    // Stale attachment cleanup must not fail the current save.
                }
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

    protected function putAttachmentContent($disk, string $path, string $content, string $mimeType): bool
    {
        if ($this->putAttachmentStream($disk, $path, $content, [
            'ContentType' => $mimeType,
        ])) {
            return true;
        }

        if ($this->putAttachmentStream($disk, $path, $content)) {
            return true;
        }

        try {
            return $disk->put($path, $content) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    protected function attachmentUploadDisks(string $primaryDiskName, $primaryDisk = null): array
    {
        $diskNames = collect([
            $primaryDiskName,
            'yandex',
            's3',
        ])
            ->filter()
            ->unique()
            ->values();

        return $diskNames
            ->map(function (string $diskName) use ($primaryDiskName, $primaryDisk) {
                try {
                    return [
                        $diskName,
                        $diskName === $primaryDiskName && $primaryDisk
                            ? $primaryDisk
                            : Storage::disk($diskName),
                    ];
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function putAttachmentStream($disk, string $path, string $content, array $options = []): bool
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            return false;
        }

        try {
            fwrite($stream, $content);
            rewind($stream);

            return $disk->put($path, $stream, $options) !== false;
        } catch (Throwable) {
            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
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
            $mailMessage->attachments
                ->map(function (MailMessageAttachment $attachment, int $index) {
                    $name = $attachment->original_name ?: $attachment->file_name;
                    $isImage = $this->isImageAttachment($attachment->mime_type, $name);
                    $isPdf = $this->isPdfAttachment($attachment->mime_type, $name);

                    return [
                        'id' => $attachment->id,
                        'index' => $index,
                        'original_name' => $attachment->original_name,
                        'file_name' => $attachment->file_name,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                        'content_id' => $attachment->content_id,
                        'disposition' => $attachment->disposition,
                        'is_image' => $isImage,
                        'is_pdf' => $isPdf,
                        'is_saved' => true,
                        'disk' => $attachment->disk,
                        'path' => $attachment->path,
                        'folder_path' => $attachment->folder_path,
                        'folder_url' => $attachment->folder_url,
                        'url' => $attachment->url,
                        'preview_url' => ($isImage || $isPdf) ? $attachment->url : null,
                        'saved_to_disk_at' => $attachment->saved_to_disk_at,
                    ];
                })
                ->values()
                ->all()
        );

        return $mailMessage;
    }

    protected function withMailSyncError(MailMessage $mailMessage, string $message): MailMessage
    {
        $mailMessage->loadMissing(['attachments', 'notes.user', 'leads']);
        $mailMessage->setAttribute('mail_sync_error', $message);

        return $this->withAvailableAttachments($mailMessage);
    }

    protected function attachmentPayloads(MailMessage $mailMessage, $message): array
    {
        $mailMessage->loadMissing('attachments');

        return collect($this->messageAttachments($message))
            ->values()
            ->map(function ($attachment, int $index) use ($mailMessage) {
                try {
                    $name = $this->attachmentName($attachment, $index);
                    $content = $this->attachmentContent($attachment);
                    $size = is_string($content) ? strlen($content) : null;
                    $mimeType = $this->attachmentMimeType($attachment);
                    $saved = $this->matchingSavedAttachment($mailMessage, $name, $size);
                    $isImage = $this->isImageAttachment($mimeType, $name);
                    $isPdf = $this->isPdfAttachment($mimeType, $name);

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
                        'is_pdf' => $isPdf,
                        'is_saved' => (bool) $saved,
                        'disk' => $saved?->disk,
                        'path' => $saved?->path,
                        'folder_path' => $saved?->folder_path,
                        'folder_url' => $saved?->folder_url,
                        'url' => $saved?->url,
                        'preview_url' => $this->attachmentPreviewUrl($content, $mimeType, $isImage, $isPdf, $saved),
                        'saved_to_disk_at' => $saved?->saved_to_disk_at,
                    ];
                } catch (Throwable $exception) {
                    logger()->warning('Yandex attachment payload failed', [
                        'mail_message_id' => $mailMessage->id,
                        'attachment_index' => $index,
                        'message' => $exception->getMessage(),
                    ]);

                    return [
                        'id' => null,
                        'index' => $index,
                        'original_name' => 'attachment-' . ($index + 1) . '.bin',
                        'file_name' => 'attachment-' . ($index + 1) . '.bin',
                        'mime_type' => 'application/octet-stream',
                        'size' => null,
                        'content_id' => null,
                        'disposition' => null,
                        'is_image' => false,
                        'is_pdf' => false,
                        'is_saved' => false,
                        'disk' => null,
                        'path' => null,
                        'folder_path' => null,
                        'folder_url' => null,
                        'url' => null,
                        'preview_url' => null,
                        'saved_to_disk_at' => null,
                    ];
                }
            })
            ->values()
            ->all();
    }

    protected function savedAttachmentPayload(?MailMessageAttachment $attachment, int $index): ?array
    {
        if (!$attachment) {
            return null;
        }

        $name = $attachment->original_name ?: $attachment->file_name;
        $isImage = $this->isImageAttachment($attachment->mime_type, $name);
        $isPdf = $this->isPdfAttachment($attachment->mime_type, $name);

        return [
            'id' => $attachment->id,
            'index' => $index,
            'original_name' => $attachment->original_name,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'content_id' => $attachment->content_id,
            'disposition' => $attachment->disposition,
            'is_image' => $isImage,
            'is_pdf' => $isPdf,
            'is_saved' => true,
            'disk' => $attachment->disk,
            'path' => $attachment->path,
            'folder_path' => $attachment->folder_path,
            'folder_url' => $attachment->folder_url,
            'url' => $attachment->url,
            'preview_url' => ($isImage || $isPdf) ? $attachment->url : null,
            'saved_to_disk_at' => $attachment->saved_to_disk_at,
        ];
    }

    protected function replaceCidImageSources(?string $html, array $attachments): ?string
    {
        if (!$html || !str_contains($html, 'cid:')) {
            return $html;
        }

        $cidMap = collect($attachments)
            ->filter(fn (array $attachment) => !empty($attachment['content_id']))
            ->mapWithKeys(function (array $attachment) {
                $cid = $this->normalizeCid((string) $attachment['content_id']);
                $url = $attachment['preview_url'] ?? $attachment['url'] ?? null;

                return $cid && $url ? [$cid => $url] : [];
            })
            ->all();

        $emptyImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])cid:([^"\']+)(["\'][^>]*>)/i',
            function (array $matches) use ($cidMap, $emptyImage) {
                $cid = $this->normalizeCid($matches[2]);
                $url = $cidMap[$cid] ?? $emptyImage;

                return $matches[1] . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $matches[3];
            },
            $html
        );
    }

    protected function normalizeCid(?string $cid): ?string
    {
        $cid = trim(rawurldecode((string) $cid));
        $cid = trim($cid, '<> ');

        return $cid !== '' ? strtolower($cid) : null;
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

        return trim($folder, '/');
    }

    protected function mailMessageUnitFolders(?MailMessage $mailMessage, string $diskName): array
    {
        if (!$mailMessage) {
            return [];
        }

        $mailMessage->loadMissing(['emails.units:id,name', 'emails.entities.units:id,name']);

        return $mailMessage->emails
            ->flatMap(fn (Email $email) => $email->units->merge(
                $email->entities->flatMap(fn ($entity) => $entity->units)
            ))
            ->filter()
            ->flatMap(fn ($unit) => [
                [
                    'path' => 'units/' . $unit->id,
                    'disk' => $diskName,
                ],
                $unit->name ? [
                    'path' => 'units/' . $this->safeAttachmentName((string) $unit->name),
                    'disk' => $diskName,
                ] : null,
            ])
            ->filter()
            ->values()
            ->all();
    }

    protected function storedAttachmentFolders(?string $diskName = null): array
    {
        $query = MailMessageAttachment::query()
            ->whereNotNull('path')
            ->select(['disk', 'path']);

        if ($diskName) {
            $query->where('disk', $diskName);
        }

        return $query
            ->get()
            ->map(fn (MailMessageAttachment $attachment) => [
                'path' => trim(dirname((string) $attachment->path), '/.'),
                'disk' => $attachment->disk ?: $this->attachmentsDiskName(),
            ])
            ->filter(fn (array $folder) => $folder['path'] !== '')
            ->values()
            ->all();
    }

    protected function storageAttachmentFolders($disk, string $root): array
    {
        try {
            if (method_exists($disk, 'allDirectories')) {
                return $root === ''
                    ? $disk->allDirectories()
                    : $disk->allDirectories($root);
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

        $directories = $path === ''
            ? $disk->directories()
            : $disk->directories($path);

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
            'disk' => $diskName,
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

    protected function attachmentPreviewUrl(
        ?string $content,
        string $mimeType,
        bool $isImage,
        bool $isPdf,
        ?MailMessageAttachment $saved
    ): ?string
    {
        if ($saved?->url && ($isImage || $isPdf)) {
            return $saved->url;
        }

        if ((!$isImage && !$isPdf) || !is_string($content) || $content === '') {
            return null;
        }

        $maxInlinePreviewSize = $isPdf ? 10 * 1024 * 1024 : 5 * 1024 * 1024;

        if (strlen($content) > $maxInlinePreviewSize) {
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

    protected function isPdfAttachment(?string $mimeType, ?string $name = null): bool
    {
        return strtolower((string) $mimeType) === 'application/pdf'
            || (bool) preg_match('/\.pdf$/i', (string) $name);
    }

    protected function mailMessageHeadersSuggestAttachments(MailMessage $mailMessage): bool
    {
        return $this->messageHeadersSuggestAttachments($mailMessage->raw_headers);
    }

    protected function messageHeadersSuggestAttachments($source): bool
    {
        if ($source instanceof MailMessage) {
            $headers = $source->raw_headers;
        } elseif (is_object($source)) {
            $headers = $this->stringValue($source->header->raw ?? null);
        } else {
            $headers = $this->stringValue($source);
        }

        if (!$headers) {
            return false;
        }

        $headers = strtolower($headers);

        return str_contains($headers, 'multipart/mixed')
            || str_contains($headers, 'content-disposition: attachment')
            || str_contains($headers, 'filename=')
            || str_contains($headers, 'filename*=')
            || str_contains($headers, 'x-ms-has-attach: yes')
            || str_contains($headers, 'x-attachment-id:');
    }

    protected function messageAttachments($message): array
    {
        $attachments = [];

        foreach (['getAttachments', 'attachments'] as $methodOrProperty) {
            try {
                $value = null;

                if (is_object($message) && method_exists($message, $methodOrProperty)) {
                    $value = $message->{$methodOrProperty}();
                } elseif (is_object($message) && isset($message->{$methodOrProperty})) {
                    $value = $message->{$methodOrProperty};
                }

                $attachments = [
                    ...$attachments,
                    ...$this->attachmentsFromValue($value),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        $attachments = [
            ...$attachments,
            ...$this->messagePartAttachments($message),
        ];

        return $this->uniqueMessageAttachments($attachments);
    }

    protected function attachmentsFromValue($value): array
    {
        if ($value instanceof \Traversable) {
            return array_values(iterator_to_array($value));
        }

        if (is_array($value)) {
            return array_values($value);
        }

        foreach (['all', 'toArray'] as $method) {
            if (is_object($value) && method_exists($value, $method)) {
                $items = $value->{$method}();

                return is_array($items) ? array_values($items) : [];
            }
        }

        return [];
    }

    protected function messagePartAttachments($message): array
    {
        return collect($this->messageStructureParts($message))
            ->filter(fn ($part) => $this->partLooksLikeAttachment($part))
            ->map(fn ($part, int $index) => $this->attachmentFromMessagePart($message, $part, $index))
            ->filter()
            ->values()
            ->all();
    }

    protected function messageStructureParts($message): array
    {
        $structure = null;

        if (is_object($message) && method_exists($message, 'getStructure')) {
            try {
                $structure = $message->getStructure();
            } catch (Throwable) {
                $structure = null;
            }
        }

        if (!$structure && is_object($message)) {
            try {
                $reflection = new ReflectionObject($message);

                if ($reflection->hasProperty('structure')) {
                    $property = $reflection->getProperty('structure');
                    $property->setAccessible(true);
                    $structure = $property->getValue($message);
                }
            } catch (Throwable) {
                $structure = null;
            }
        }

        $parts = is_object($structure) ? ($structure->parts ?? null) : null;

        if ($parts instanceof \Traversable) {
            return array_values(iterator_to_array($parts));
        }

        return is_array($parts) ? array_values($parts) : [];
    }

    protected function partLooksLikeAttachment($part): bool
    {
        if (!is_object($part) && !is_array($part)) {
            return false;
        }

        try {
            if (is_object($part) && method_exists($part, 'isAttachment') && $part->isAttachment()) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to header-based detection.
        }

        $name = $this->partAttachmentName($part);
        $mimeType = strtolower((string) $this->partMimeType($part, $name));
        $disposition = strtolower((string) $this->partString($part, 'disposition'));
        $contentId = $this->partContentId($part);

        if ($name) {
            return true;
        }

        if ($disposition === 'attachment') {
            return true;
        }

        if ($disposition === 'inline' && $contentId) {
            return true;
        }

        return $this->isDocumentAttachment($mimeType, $name);
    }

    protected function attachmentFromMessagePart($message, $part, int $index)
    {
        try {
            if (
                class_exists(\Webklex\PHPIMAP\Message::class)
                && class_exists(\Webklex\PHPIMAP\Part::class)
                && class_exists(\Webklex\PHPIMAP\Attachment::class)
                && $message instanceof \Webklex\PHPIMAP\Message
                && $part instanceof \Webklex\PHPIMAP\Part
            ) {
                $attachment = new \Webklex\PHPIMAP\Attachment($message, $part);

                if ($this->attachmentContent($attachment) !== null) {
                    return $attachment;
                }
            }
        } catch (Throwable) {
            // Some malformed MIME parts cannot be wrapped by Webklex; use the raw part fallback below.
        }

        return $this->partAttachmentPayload($part, $index);
    }

    protected function partAttachmentPayload($part, int $index): ?array
    {
        $content = $this->partRawValue($part, 'content');

        if (!is_string($content)) {
            return null;
        }

        $content = $this->decodePartContent($content, $this->partRawValue($part, 'encoding'));

        if ($content === '') {
            return null;
        }

        $name = $this->partAttachmentName($part, $index) ?: 'attachment-' . ($index + 1) . '.bin';
        $mimeType = $this->partMimeType($part, $name) ?: 'application/octet-stream';

        return [
            'name' => $name,
            'filename' => $name,
            'content' => $content,
            'mime_type' => $mimeType,
            'content_id' => $this->partContentId($part),
            'disposition' => $this->partString($part, 'disposition'),
            'part_number' => $this->partRawValue($part, 'part_number'),
        ];
    }

    protected function partAttachmentName($part, ?int $index = null): ?string
    {
        foreach (['filename', 'name', 'file_name'] as $property) {
            $name = $this->decodeMime($this->stringValue($this->partRawValue($part, $property)));

            if ($name) {
                return $name;
            }
        }

        foreach (['filename', 'name'] as $header) {
            $name = $this->decodeMime($this->partHeaderString($part, $header));

            if ($name) {
                return $name;
            }
        }

        return $index === null ? null : 'attachment-' . ($index + 1) . '.bin';
    }

    protected function partMimeType($part, ?string $name = null): ?string
    {
        $mimeType = $this->stringValue($this->partRawValue($part, 'content_type'))
            ?: $this->partHeaderString($part, 'content_type')
            ?: $this->mimeTypeFromName($name);

        if (!$mimeType) {
            return null;
        }

        return strtolower(trim(explode(';', $mimeType)[0]));
    }

    protected function partContentId($part): ?string
    {
        $contentId = $this->stringValue($this->partRawValue($part, 'content_id'))
            ?: $this->stringValue($this->partRawValue($part, 'id'))
            ?: $this->partHeaderString($part, 'content_id')
            ?: $this->partHeaderString($part, 'x_attachment_id');

        return $contentId ? trim($contentId, '<>') : null;
    }

    protected function partString($part, string $property): ?string
    {
        return $this->stringValue($this->partRawValue($part, $property));
    }

    protected function partRawValue($part, string $property)
    {
        if (is_array($part)) {
            return $part[$property] ?? null;
        }

        if (!is_object($part)) {
            return null;
        }

        try {
            return $part->{$property} ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function partHeaderString($part, string $header): ?string
    {
        if (!is_object($part) || !method_exists($part, 'getHeader')) {
            return null;
        }

        try {
            $partHeader = $part->getHeader();

            if (!$partHeader || !method_exists($partHeader, 'get')) {
                return null;
            }

            $attribute = $partHeader->get($header);
            $value = $this->firstAttributeValue($attribute) ?? $this->stringValue($attribute);

            return $this->stringValue($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function decodePartContent(string $content, $encoding): string
    {
        $encodingName = is_numeric($encoding) ? (int) $encoding : strtolower((string) $encoding);

        if (
            $encodingName === 'base64'
            || (
                class_exists(\Webklex\PHPIMAP\IMAP::class)
                && $encodingName === \Webklex\PHPIMAP\IMAP::MESSAGE_ENC_BASE64
            )
        ) {
            $normalized = preg_replace('/\s+/', '', $content) ?: $content;
            $decoded = base64_decode($normalized, true);

            if (is_string($decoded)) {
                return $decoded;
            }

            $decoded = base64_decode($normalized, false);

            return is_string($decoded) ? $decoded : $content;
        }

        if (
            $encodingName === 'quoted-printable'
            || (
                class_exists(\Webklex\PHPIMAP\IMAP::class)
                && $encodingName === \Webklex\PHPIMAP\IMAP::MESSAGE_ENC_QUOTED_PRINTABLE
            )
        ) {
            return quoted_printable_decode($content);
        }

        return $content;
    }

    protected function uniqueMessageAttachments(array $attachments): array
    {
        $seen = [];
        $unique = [];

        foreach ($attachments as $index => $attachment) {
            if (!$attachment) {
                continue;
            }

            $key = $this->messageAttachmentKey($attachment, $index);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $attachment;
        }

        return $unique;
    }

    protected function messageAttachmentKey($attachment, int $index): string
    {
        $partNumber = $this->attachmentRawValue($attachment, 'part_number');

        if ($partNumber !== null && $partNumber !== '') {
            return 'part:' . $partNumber;
        }

        $contentId = $this->attachmentContentId($attachment);

        if ($contentId) {
            return 'cid:' . strtolower($contentId);
        }

        $name = strtolower($this->attachmentName($attachment, $index));
        $content = $this->attachmentContent($attachment);
        $size = is_string($content) ? strlen($content) : 'unknown';

        return 'file:' . $name . ':' . $size;
    }

    protected function attachmentRawValue($attachment, string $property)
    {
        if (is_array($attachment)) {
            return $attachment[$property] ?? null;
        }

        if (!is_object($attachment)) {
            return null;
        }

        try {
            return $attachment->{$property} ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function isDocumentAttachment(?string $mimeType, ?string $name = null): bool
    {
        $mimeType = strtolower((string) $mimeType);

        if ($mimeType && (
            str_starts_with($mimeType, 'application/')
            || str_starts_with($mimeType, 'image/')
            || str_starts_with($mimeType, 'audio/')
            || str_starts_with($mimeType, 'video/')
            || in_array($mimeType, ['text/csv', 'text/calendar'], true)
        )) {
            return true;
        }

        return (bool) preg_match('/\.(pdf|docx?|xlsx?|xlsm|csv|pptx?|zip|rar|7z|txt|rtf)$/i', (string) $name);
    }

    protected function mimeTypeFromName(?string $name): ?string
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'csv' => 'text/csv',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
            'rtf' => 'application/rtf',
            'txt' => 'text/plain',
            default => null,
        };
    }

    protected function messageHasAttachments($message): bool
    {
        if (! is_object($message)) {
            return false;
        }

        foreach (['hasAttachments', 'has_attachments'] as $methodOrProperty) {
            try {
                if (method_exists($message, $methodOrProperty)) {
                    if ((bool) $message->{$methodOrProperty}()) {
                        return true;
                    }

                    continue;
                }

                if (isset($message->{$methodOrProperty}) && (bool) $message->{$methodOrProperty}) {
                    return true;
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
        $mimeFromName = $this->mimeTypeFromName($this->attachmentName($attachment, 0));

        if ($mimeFromName) {
            return $mimeFromName;
        }

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

        return $this->mimeTypeFromName($this->attachmentName($attachment, 0)) ?: 'application/octet-stream';
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
