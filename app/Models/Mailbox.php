<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Mailbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'name',
        'from_name',
        'is_default',
        'is_active',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_inbox',
        'imap_sent',
        'imap_folders',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'smtp_timeout',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'imap_port' => 'integer',
        'imap_password' => 'encrypted',
        'imap_folders' => 'array',
        'smtp_port' => 'integer',
        'smtp_password' => 'encrypted',
        'smtp_timeout' => 'integer',
    ];

    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['address'] = Str::lower(trim((string) $value));
    }

    public function toRegistryArray(): array
    {
        return [
            'id' => $this->id,
            'source' => 'database',
            'can_edit' => true,
            'address' => $this->address,
            'name' => $this->name ?: $this->address,
            'from_name' => $this->from_name ?: ($this->name ?: $this->address),
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'imap' => [
                'host' => $this->imap_host,
                'port' => $this->imap_port ?: 993,
                'encryption' => $this->imap_encryption ?: 'ssl',
                'username' => $this->imap_username ?: $this->address,
                'password' => $this->imap_password,
                'inbox' => $this->imap_inbox ?: 'INBOX',
                'sent' => $this->imap_sent ?: 'Sent',
            ],
            'smtp' => [
                'host' => $this->smtp_host,
                'port' => $this->smtp_port ?: 465,
                'encryption' => $this->smtp_encryption ?: 'ssl',
                'username' => $this->smtp_username ?: $this->address,
                'password' => $this->smtp_password,
                'timeout' => $this->smtp_timeout ?: 30,
            ],
            'folders' => $this->folders(),
        ];
    }

    public function folders(): array
    {
        return collect($this->imap_folders ?: [])
            ->map(fn ($folder) => trim((string) $folder))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
