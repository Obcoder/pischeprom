<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Services\Mail\MailboxRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MailboxController extends Controller
{
    public function index(Request $request, MailboxRegistry $mailboxes): JsonResponse
    {
        $management = $request->boolean('management');

        return response()->json([
            'data' => $mailboxes->publicMailboxes(
                includeInactive: $management || $request->boolean('include_inactive'),
                management: $management,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateMailbox($request);

        $mailbox = Mailbox::query()->create($this->payload($data));
        $this->syncDefaultFlag($mailbox);

        return response()->json([
            'data' => $this->serialize($mailbox->fresh()),
            'message' => 'Почтовый ящик создан.',
        ], 201);
    }

    public function show(Mailbox $mailbox): JsonResponse
    {
        return response()->json([
            'data' => $this->serialize($mailbox),
        ]);
    }

    public function update(Request $request, Mailbox $mailbox): JsonResponse
    {
        $data = $this->validateMailbox($request, $mailbox);

        $mailbox->fill($this->payload($data, $mailbox));
        $mailbox->save();
        $this->syncDefaultFlag($mailbox);

        return response()->json([
            'data' => $this->serialize($mailbox->fresh()),
            'message' => 'Почтовый ящик обновлён.',
        ]);
    }

    public function destroy(Mailbox $mailbox): JsonResponse
    {
        $mailbox->delete();

        return response()->json([
            'message' => 'Почтовый ящик удалён.',
        ]);
    }

    private function validateMailbox(Request $request, ?Mailbox $mailbox = null): array
    {
        $request->merge([
            'address' => Str::lower(trim((string) $request->input('address'))),
            'imap_folders' => $this->normalizeFolders($request->input('imap_folders')),
        ]);

        return $request->validate([
            'address' => [
                'required',
                'email',
                'max:255',
                Rule::unique('mailboxes', 'address')->ignore($mailbox?->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],

            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', 'string', 'max:32'],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:2000'],
            'imap_inbox' => ['nullable', 'string', 'max:255'],
            'imap_sent' => ['nullable', 'string', 'max:255'],
            'imap_folders' => ['nullable', 'array'],
            'imap_folders.*' => ['string', 'max:255'],
            'clear_imap_password' => ['nullable', 'boolean'],

            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', 'max:32'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:2000'],
            'smtp_timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'clear_smtp_password' => ['nullable', 'boolean'],
        ]);
    }

    private function payload(array $data, ?Mailbox $mailbox = null): array
    {
        $address = $data['address'];

        $payload = [
            'address' => $address,
            'name' => $this->nullableString($data['name'] ?? null),
            'from_name' => $this->nullableString($data['from_name'] ?? null),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'imap_host' => $this->nullableString($data['imap_host'] ?? null),
            'imap_port' => (int) ($data['imap_port'] ?? 993),
            'imap_encryption' => $this->nullableString($data['imap_encryption'] ?? 'ssl') ?: 'ssl',
            'imap_username' => $this->nullableString($data['imap_username'] ?? null) ?: $address,
            'imap_inbox' => $this->nullableString($data['imap_inbox'] ?? null) ?: 'INBOX',
            'imap_sent' => $this->nullableString($data['imap_sent'] ?? null) ?: 'Sent',
            'imap_folders' => $this->normalizeFolders($data['imap_folders'] ?? []),
            'smtp_host' => $this->nullableString($data['smtp_host'] ?? null),
            'smtp_port' => (int) ($data['smtp_port'] ?? 465),
            'smtp_encryption' => $this->nullableString($data['smtp_encryption'] ?? 'ssl') ?: 'ssl',
            'smtp_username' => $this->nullableString($data['smtp_username'] ?? null) ?: $address,
            'smtp_timeout' => (int) ($data['smtp_timeout'] ?? 30),
        ];

        if (array_key_exists('imap_password', $data) && filled($data['imap_password'])) {
            $payload['imap_password'] = $data['imap_password'];
        } elseif (($data['clear_imap_password'] ?? false) || ! $mailbox) {
            $payload['imap_password'] = null;
        }

        if (array_key_exists('smtp_password', $data) && filled($data['smtp_password'])) {
            $payload['smtp_password'] = $data['smtp_password'];
        } elseif (($data['clear_smtp_password'] ?? false) || ! $mailbox) {
            $payload['smtp_password'] = null;
        }

        return $payload;
    }

    private function serialize(Mailbox $mailbox): array
    {
        $mailbox = $mailbox->fresh() ?: $mailbox;

        return [
            'id' => $mailbox->id,
            'source' => 'database',
            'can_edit' => true,
            'address' => $mailbox->address,
            'name' => $mailbox->name ?: $mailbox->address,
            'from_name' => $mailbox->from_name ?: ($mailbox->name ?: $mailbox->address),
            'label' => trim(($mailbox->name ?: $mailbox->address).' <'.$mailbox->address.'>'),
            'is_default' => $mailbox->is_default,
            'is_active' => $mailbox->is_active,
            'imap' => [
                'host' => $mailbox->imap_host,
                'port' => $mailbox->imap_port ?: 993,
                'encryption' => $mailbox->imap_encryption ?: 'ssl',
                'username' => $mailbox->imap_username ?: $mailbox->address,
                'inbox' => $mailbox->imap_inbox ?: 'INBOX',
                'sent' => $mailbox->imap_sent ?: 'Sent',
            ],
            'smtp' => [
                'host' => $mailbox->smtp_host,
                'port' => $mailbox->smtp_port ?: 465,
                'encryption' => $mailbox->smtp_encryption ?: 'ssl',
                'username' => $mailbox->smtp_username ?: $mailbox->address,
                'timeout' => $mailbox->smtp_timeout ?: 30,
            ],
            'folders' => $mailbox->folders(),
            'has_imap_password' => filled($mailbox->imap_password),
            'has_smtp_password' => filled($mailbox->smtp_password),
        ];
    }

    private function syncDefaultFlag(Mailbox $mailbox): void
    {
        if (! $mailbox->is_default) {
            return;
        }

        Mailbox::query()
            ->whereKeyNot($mailbox->getKey())
            ->update(['is_default' => false]);
    }

    private function normalizeFolders(mixed $folders): array
    {
        if (is_string($folders)) {
            $folders = preg_split('/[\r\n,]+/', $folders) ?: [];
        }

        return collect(Arr::wrap($folders))
            ->map(fn ($folder) => trim((string) $folder))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || Str::lower($value) === 'null' ? null : $value;
    }
}
