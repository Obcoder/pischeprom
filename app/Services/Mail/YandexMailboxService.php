<?php

namespace App\Services\Mail;

use App\Models\Email;
use App\Models\MailMessage;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\ClientManager;

class YandexMailboxService
{
    public function syncAll(int $limit = 1000): array
    {
        $inbox = config('services.yandex_mail.imap.inbox', 'INBOX');
        $sent = config('services.yandex_mail.imap.sent', 'Sent');

        return [
            'incoming' => $this->syncFolder($inbox, 'incoming', $limit),
            'outgoing' => $this->syncFolder($sent, 'outgoing', $limit),
        ];
    }

    public function syncFolder(string $folderName, string $direction, int $limit = 1000): int
    {
        $client = $this->client();
        $client->connect();

        try {
            $folder = $client->getFolder($folderName);

            $total = $folder->query()->all()->count();

            if ($total === 0) {
                return 0;
            }

            $pageSize = 25;
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

                    $this->storeMessage($message, $folderName, $direction);
                    $stored++;
                }
            }

            return $stored;
        } finally {
            $client->disconnect();
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

    protected function storeMessage($message, string $folderName, string $direction): void
    {
        $from = Arr::first($this->addresses($this->attribute($message, 'from')));
        $to = $this->addresses($this->attribute($message, 'to'));
        $cc = $this->addresses($this->attribute($message, 'cc'));

        $messageId = $this->stringValue($this->firstAttributeValue($this->attribute($message, 'message_id')));
        $subject = $this->decodeMime(
            $this->stringValue($this->firstAttributeValue($this->attribute($message, 'subject')))
        );
        $messageDate = $this->dateValue($this->firstAttributeValue($this->attribute($message, 'date')));

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

        $mailMessage = MailMessage::updateOrCreate(
            [
                'mailbox' => config('services.yandex_mail.address'),
                'folder' => $folderName,
                'imap_uid' => $imapUid,
            ],
            [
                'direction' => $direction,
                'message_id' => $messageId,
                'subject' => $subject,
                'message_date' => $messageDate,
                'from_address' => $from['address'] ?? null,
                'from_name' => $from['name'] ?? null,
                'to' => $to,
                'cc' => $cc,
                'preview' => null,
                'has_attachments' => false,
                'raw_headers' => $message->header->raw ?? null,
            ]
        );

        if ($direction === 'incoming' && !empty($from['address'])) {
            $email = $this->findOrCreateEmail($from['address'], $from['name'] ?? null);
            $this->attachRole($mailMessage, $email, 'from');
        }

        if ($direction === 'outgoing') {
            foreach ($to as $address) {
                $email = $this->findOrCreateEmail($address['address'], $address['name'] ?? null);
                $this->attachRole($mailMessage, $email, 'to');
            }

            foreach ($cc as $address) {
                $email = $this->findOrCreateEmail($address['address'], $address['name'] ?? null);
                $this->attachRole($mailMessage, $email, 'cc');
            }
        }
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

    public function loadBody(MailMessage $mailMessage, bool $force = false): MailMessage
    {
        if (!$force && $mailMessage->body_loaded_at) {
            return $mailMessage;
        }

        $client = $this->client();
        $client->connect();

        try {
            $folder = $this->resolveFolder($client, $mailMessage->folder);

            if (!$folder) {
                logger()->warning('Yandex load body folder not found', [
                    'mail_message_id' => $mailMessage->id,
                    'folder' => $mailMessage->folder,
                    'imap_uid' => $mailMessage->imap_uid,
                ]);

                return $mailMessage;
            }

            $message = $folder
                ->query()
                ->leaveUnread()
                ->setFetchBody(true)
                ->getMessageByUid((int) $mailMessage->imap_uid);

            if (!$message) {
                logger()->warning('Yandex message body not found by uid', [
                    'mail_message_id' => $mailMessage->id,
                    'folder' => $mailMessage->folder,
                    'imap_uid' => $mailMessage->imap_uid,
                    'subject' => $mailMessage->subject,
                ]);

                return $mailMessage;
            }

            $html = $this->messageBody($message, 'html');
            $text = $this->messageBody($message, 'text');

            if (!$text && $html) {
                $text = trim(strip_tags($html));
            }

            $mailMessage->forceFill([
                                        'html' => $html,
                                        'text' => $text,
                                        'body_loaded_at' => now(),
                                    ])->save();

            return $mailMessage->fresh();
        } catch (\Throwable $exception) {
            logger()->error('Yandex load body failed', [
                'mail_message_id' => $mailMessage->id,
                'folder' => $mailMessage->folder,
                'imap_uid' => $mailMessage->imap_uid,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $client->disconnect();
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
