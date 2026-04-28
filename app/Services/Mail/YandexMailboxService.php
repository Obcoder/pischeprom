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

        $mailMessage = MailMessage::updateOrCreate(
            [
                'mailbox' => config('services.yandex_mail.address'),
                'folder' => $folderName,
                'imap_uid' => $this->uid($message),
            ],
            [
                'direction' => $direction,
                'message_id' => $this->stringValue($this->call($message, 'getMessageId')),
                'subject' => $this->stringValue($this->call($message, 'getSubject')),
                'message_date' => $this->dateValue($this->call($message, 'getDate')),
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
        $uid = $this->call($message, 'getUid');
        $value = $this->stringValue($uid);

        if ($value) {
            return (int) $value;
        }

        $fallback = implode('|', [
            $this->stringValue($this->call($message, 'getMessageId')),
            $this->stringValue($this->call($message, 'getSubject')),
            $this->stringValue($this->call($message, 'getDate')),
        ]);

        return abs(crc32($fallback));
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

        if (is_object($value) && method_exists($value, 'first')) {
            return $this->stringValue($value->first());
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
        }

        if (is_object($collection) && method_exists($collection, 'toArray')) {
            $collection = $collection->toArray();
        }

        if (!is_iterable($collection)) {
            return [];
        }

        $addresses = [];

        foreach ($collection as $item) {
            $address = $item['mail']
                ?? $item['email']
                ?? $item->mail
                ?? $item->email
                ?? null;

            if (!$address) {
                continue;
            }

            $addresses[] = [
                'address' => Str::lower(trim($address)),
                'name' => $item['personal']
                    ?? $item['name']
                        ?? $item->personal
                        ?? $item->name
                        ?? null,
            ];
        }

        return collect($addresses)
            ->unique('address')
            ->values()
            ->all();
    }
}
