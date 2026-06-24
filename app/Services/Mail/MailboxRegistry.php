<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MailboxRegistry
{
    public function all(bool $includeInactive = false): array
    {
        $configured = collect(config('services.yandex_mail.mailboxes', []))
            ->map(fn ($mailbox) => is_array($mailbox) ? $this->normalizeMailbox($mailbox) : null)
            ->filter()
            ->unique('address')
            ->values()
            ->all();

        if (empty($configured)) {
            $legacy = $this->normalizeMailbox([
                'address' => config('services.yandex_mail.address'),
                'name' => config('mail.from.name'),
                'from_name' => config('mail.from.name'),
                'imap' => config('services.yandex_mail.imap', []),
                'smtp' => config('mail.mailers.smtp', []),
                'folders' => config('services.yandex_mail.folders', []),
            ]);

            $configured = $legacy ? [$legacy] : [];
        }

        $mailboxes = collect($configured)->keyBy('address');

        foreach ($this->databaseMailboxes($includeInactive) as $mailbox) {
            $mailboxes->put($mailbox['address'], $mailbox);
        }

        return $this->applyDefault($mailboxes->values()->all());
    }

    public function publicMailboxes(bool $includeInactive = false, bool $management = false): array
    {
        return collect($this->all($includeInactive))
            ->map(fn (array $mailbox) => $this->publicMailbox($mailbox, $management))
            ->values()
            ->all();
    }

    public function default(): ?array
    {
        $mailboxes = $this->all();

        return collect($mailboxes)->first(fn (array $mailbox) => (bool) ($mailbox['is_default'] ?? false))
            ?: ($mailboxes[0] ?? null);
    }

    public function find(?string $address): ?array
    {
        $address = $this->normalizeEmail($address);

        if (! $address) {
            return null;
        }

        return collect($this->all())->firstWhere('address', $address);
    }

    public function findOrDefault(?string $address): array
    {
        $mailbox = $this->find($address) ?: $this->default();

        if (! $mailbox) {
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

        if (! $mailbox) {
            return (string) config('mail.default', 'log');
        }

        $smtp = $mailbox['smtp'] ?? [];

        if (blank($smtp['host'] ?? null) || blank($smtp['username'] ?? null)) {
            return (string) config('mail.default', 'log');
        }

        $name = 'mailbox_'.substr(sha1($mailbox['address']), 0, 12);

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

        if (! $mailbox) {
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

        if (! $address) {
            return null;
        }

        $name = trim((string) ($mailbox['name'] ?? '')) ?: $address;
        $fromName = trim((string) ($mailbox['from_name'] ?? '')) ?: $name;

        return [
            'id' => $mailbox['id'] ?? null,
            'source' => $mailbox['source'] ?? 'config',
            'can_edit' => (bool) ($mailbox['can_edit'] ?? false),
            'address' => $address,
            'name' => $name,
            'from_name' => $fromName,
            'is_default' => (bool) ($mailbox['is_default'] ?? false),
            'is_active' => (bool) ($mailbox['is_active'] ?? true),
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

    private function databaseMailboxes(bool $includeInactive = false): array
    {
        try {
            if (! Schema::hasTable('mailboxes')) {
                return [];
            }

            return Mailbox::query()
                ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
                ->orderByDesc('is_default')
                ->orderBy('address')
                ->get()
                ->map(fn (Mailbox $mailbox) => $this->normalizeMailbox($mailbox->toRegistryArray()))
                ->filter()
                ->values()
                ->all();
        } catch (Throwable $exception) {
            logger()->debug('Mailboxes table is not available for registry lookup', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function applyDefault(array $mailboxes): array
    {
        $hasExplicitDefault = collect($mailboxes)
            ->contains(fn (array $mailbox) => (bool) ($mailbox['is_default'] ?? false));

        return collect($mailboxes)
            ->values()
            ->map(function (array $mailbox, int $index) use ($hasExplicitDefault) {
                $mailbox['is_default'] = $hasExplicitDefault
                    ? (bool) ($mailbox['is_default'] ?? false)
                    : $index === 0;

                return $mailbox;
            })
            ->sortByDesc(fn (array $mailbox) => (bool) ($mailbox['is_default'] ?? false))
            ->values()
            ->all();
    }

    private function publicMailbox(array $mailbox, bool $management = false): array
    {
        $payload = [
            'id' => $mailbox['id'] ?? null,
            'source' => $mailbox['source'] ?? 'config',
            'can_edit' => (bool) ($mailbox['can_edit'] ?? false),
            'address' => $mailbox['address'],
            'name' => $mailbox['name'],
            'from_name' => $mailbox['from_name'],
            'label' => trim($mailbox['name'].' <'.$mailbox['address'].'>'),
            'is_default' => (bool) ($mailbox['is_default'] ?? false),
            'is_active' => (bool) ($mailbox['is_active'] ?? true),
        ];

        if (! $management) {
            return $payload;
        }

        return array_merge($payload, [
            'imap' => [
                'host' => Arr::get($mailbox, 'imap.host'),
                'port' => Arr::get($mailbox, 'imap.port'),
                'encryption' => Arr::get($mailbox, 'imap.encryption'),
                'username' => Arr::get($mailbox, 'imap.username'),
                'inbox' => Arr::get($mailbox, 'imap.inbox'),
                'sent' => Arr::get($mailbox, 'imap.sent'),
            ],
            'smtp' => [
                'host' => Arr::get($mailbox, 'smtp.host'),
                'port' => Arr::get($mailbox, 'smtp.port'),
                'encryption' => Arr::get($mailbox, 'smtp.encryption'),
                'username' => Arr::get($mailbox, 'smtp.username'),
                'timeout' => Arr::get($mailbox, 'smtp.timeout'),
            ],
            'folders' => $this->folders($mailbox),
            'has_imap_password' => filled(Arr::get($mailbox, 'imap.password')),
            'has_smtp_password' => filled(Arr::get($mailbox, 'smtp.password')),
        ]);
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
