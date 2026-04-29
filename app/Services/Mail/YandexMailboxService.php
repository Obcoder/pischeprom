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
        $from = Arr::first($this->addresses($this->call($message, 'getFrom')));
        $to = $this->addresses($this->call($message, 'getTo'));
        $cc = $this->addresses($this->call($message, 'getCc'));

        $messageId = $this->stringValue($this->call($message, 'getMessageId'));
        $subject = $this->stringValue($this->call($message, 'getSubject'));
        $messageDate = $this->dateValue($this->call($message, 'getDate'));

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

        \Log::info('Yandex parsed message addresses', [
            'folder' => $folderName,
            'direction' => $direction,
            'imap_uid' => $imapUid,
            'message_id' => $messageId,
            'subject' => $subject,
            'from' => $from,
            'to' => $to,
            'cc' => $cc,
        ]);

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
                'raw_headers' => null,
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

    protected function uid($message): int
    {
        foreach (['getUid', 'getUID', 'uid'] as $method) {
            if (is_object($message) && method_exists($message, $method)) {
                $value = $message->{$method}();

                if (is_numeric($value)) {
                    return (int) $value;
                }

                $string = $this->stringValue($value);

                if (is_numeric($string)) {
                    return (int) $string;
                }
            }
        }

        foreach (['uid', 'msgno', 'message_no'] as $property) {
            if (is_object($message) && isset($message->{$property}) && is_numeric($message->{$property})) {
                return (int) $message->{$property};
            }
        }

        $messageId = $this->stringValue($this->call($message, 'getMessageId'));
        $subject = $this->stringValue($this->call($message, 'getSubject'));
        $date = $this->stringValue($this->call($message, 'getDate'));

        $from = json_encode($this->addresses($this->call($message, 'getFrom')), JSON_UNESCAPED_UNICODE);
        $to = json_encode($this->addresses($this->call($message, 'getTo')), JSON_UNESCAPED_UNICODE);
        $cc = json_encode($this->addresses($this->call($message, 'getCc')), JSON_UNESCAPED_UNICODE);

        $fallback = implode('|', [
            $messageId,
            $subject,
            $date,
            $from,
            $to,
            $cc,
        ]);

        return abs(crc32($fallback ?: spl_object_hash($message)));
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

        if (is_object($value) && method_exists($value, 'first')) {
            return $this->stringValue($value->first());
        }

        if (is_object($value) && method_exists($value, 'get')) {
            return $this->stringValue($value->get());
        }

        if (is_array($value)) {
            return null;
        }

        return null;
    }

    protected function dateValue($value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        $date = $this->stringValue($value);

        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function addresses($collection): array
    {
        if (!$collection) {
            return [];
        }

        if (is_object($collection) && method_exists($collection, 'all')) {
            $collection = $collection->all();
        } elseif (is_object($collection) && method_exists($collection, 'toArray')) {
            $collection = $collection->toArray();
        } elseif (is_object($collection) && method_exists($collection, 'get')) {
            $collection = $collection->get();
        }

        if (!is_iterable($collection)) {
            $collection = [$collection];
        }

        $addresses = [];

        foreach ($collection as $item) {
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

                if (!$address && method_exists($item, 'toArray')) {
                    $array = $item->toArray();

                    $address = $array['mail']
                        ?? $array['email']
                        ?? $array['address']
                        ?? null;

                    $name = $name
                        ?? $array['personal']
                        ?? $array['name']
                        ?? null;

                    if (!$address && !empty($array['mailbox']) && !empty($array['host'])) {
                        $address = $array['mailbox'] . '@' . $array['host'];
                    }
                }

                if (!$address && method_exists($item, '__toString')) {
                    $raw = (string) $item;

                    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $matches)) {
                        $address = $matches[0];
                    }

                    if (!$name) {
                        $name = trim(str_replace($address ?? '', '', $raw), " <>\\t\\n\\r\\0\\x0B\"'");
                    }
                }
            }

            if (!$address || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $addresses[] = [
                'address' => Str::lower(trim($address)),
                'name' => $name ? trim($name) : null,
            ];
        }

        return collect($addresses)
            ->unique('address')
            ->values()
            ->all();
    }
}
