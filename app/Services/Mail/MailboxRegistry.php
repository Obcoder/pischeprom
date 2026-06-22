<?php

namespace App\Services\Mail;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class MailboxRegistry
{
    public function all(): array
    {
        $configured = collect(config('services.yandex_mail.mailboxes', []))
            ->map(fn ($mailbox) => is_array($mailbox) ? $this->normalizeMailbox($mailbox) : null)
            ->filter()
            ->unique('address')
            ->values()
            ->all();

        if (!empty($configured)) {
            return $configured;
        }

        $legacy = $this->normalizeMailbox([
            'address' => config('services.yandex_mail.address'),
            'name' => config('mail.from.name'),
            'from_name' => config('mail.from.name'),
            'imap' => config('services.yandex_mail.imap', []),
            'smtp' => config('mail.mailers.smtp', []),
            'folders' => config('services.yandex_mail.folders', []),
        ]);

        return $legacy ? [$legacy] : [];
    }

    public function publicMailboxes(): array
    {
        $default = $this->default();

        return collect($this->all())
            ->map(fn (array $mailbox) => [
                'address' => $mailbox['address'],
                'name' => $mailbox['name'],
                'label' => trim($mailbox['name'] . ' <' . $mailbox['address'] . '>'),
                'is_default' => $default && $mailbox['address'] === $default['address'],
            ])
            ->values()
            ->all();
    }

    public function default(): ?array
    {
        return $this->all()[0] ?? null;
    }

    public function find(?string $address): ?array
    {
        $address = $this->normalizeEmail($address);

        if (!$address) {
            return null;
        }

        return collect($this->all())->firstWhere('address', $address);
    }

    public function findOrDefault(?string $address): array
    {
        $mailbox = $this->find($address) ?: $this->default();

        if (!$mailbox) {
            throw new RuntimeException('Почтовый ящик не настроен.');
        }

        return $mailbox;
    }

    public function folders(array $mailbox): array
    {
        $folders = $mailbox['folders'] ?? [];

        if (empty($folders)) {
            $folders = [
                Arr::get($mailbox, 'imap.inbox', 'INBOX'),
                Arr::get($mailbox, 'imap.sent', 'Sent'),
            ];
        }

        return collect($folders)
            ->map(fn ($folder) => trim((string) $folder))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function registerMailer(array $mailbox): string
    {
        $mailbox = $this->normalizeMailbox($mailbox);

        if (!$mailbox) {
            return (string) config('mail.default', 'log');
        }

        $smtp = $mailbox['smtp'] ?? [];

        if (blank($smtp['host'] ?? null) || blank($smtp['username'] ?? null)) {
            return (string) config('mail.default', 'log');
        }

        $name = 'mailbox_' . substr(sha1($mailbox['address']), 0, 12);

        config([
            "mail.mailers.{$name}" => [
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => (int) ($smtp['port'] ?: 465),
                'encryption' => $smtp['encryption'] ?: null,
                'username' => $smtp['username'],
                'password' => $smtp['password'] ?? null,
                'timeout' => (int) ($smtp['timeout'] ?: 30),
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ],
        ]);

        return $name;
    }

    public function imapClientConfig(array $mailbox): array
    {
        $mailbox = $this->normalizeMailbox($mailbox);

        if (!$mailbox) {
            throw new RuntimeException('Почтовый ящик не настроен.');
        }

        $imap = $mailbox['imap'];

        if (blank($imap['host'] ?? null) || blank($imap['username'] ?? null)) {
            throw new RuntimeException("IMAP для {$mailbox['address']} не настроен.");
        }

        return [
            'host' => $imap['host'],
            'port' => (int) ($imap['port'] ?: 993),
            'encryption' => $imap['encryption'] ?: 'ssl',
            'validate_cert' => true,
            'username' => $imap['username'],
            'password' => $imap['password'] ?? null,
            'protocol' => 'imap',
        ];
    }

    private function normalizeMailbox(array $mailbox): ?array
    {
        $address = $this->normalizeEmail($mailbox['address'] ?? null);

        if (!$address) {
            return null;
        }

        $name = trim((string) ($mailbox['name'] ?? '')) ?: $address;
        $fromName = trim((string) ($mailbox['from_name'] ?? '')) ?: $name;

        return [
            'address' => $address,
            'name' => $name,
            'from_name' => $fromName,
            'imap' => [
                'host' => $this->nullableString(Arr::get($mailbox, 'imap.host')),
                'port' => (int) (Arr::get($mailbox, 'imap.port') ?: 993),
                'encryption' => $this->nullableString(Arr::get($mailbox, 'imap.encryption')) ?: 'ssl',
                'username' => $this->nullableString(Arr::get($mailbox, 'imap.username')) ?: $address,
                'password' => $this->nullableString(Arr::get($mailbox, 'imap.password')),
                'inbox' => $this->nullableString(Arr::get($mailbox, 'imap.inbox')) ?: 'INBOX',
                'sent' => $this->nullableString(Arr::get($mailbox, 'imap.sent')) ?: 'Sent',
            ],
            'smtp' => [
                'host' => $this->nullableString(Arr::get($mailbox, 'smtp.host')),
                'port' => (int) (Arr::get($mailbox, 'smtp.port') ?: 465),
                'encryption' => $this->nullableString(Arr::get($mailbox, 'smtp.encryption')) ?: 'ssl',
                'username' => $this->nullableString(Arr::get($mailbox, 'smtp.username')),
                'password' => $this->nullableString(Arr::get($mailbox, 'smtp.password')),
                'timeout' => (int) (Arr::get($mailbox, 'smtp.timeout') ?: 30),
            ],
            'folders' => collect($mailbox['folders'] ?? [])
                ->map(fn ($folder) => trim((string) $folder))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || Str::lower($value) === 'null' ? null : $value;
    }
}
