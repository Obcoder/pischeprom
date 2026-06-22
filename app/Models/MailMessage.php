<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox',
        'folder',
        'direction',
        'imap_uid',
        'message_id',
        'reply_to_mail_message_id',
        'in_reply_to',
        'references',
        'subject',
        'message_date',
        'from_address',
        'from_name',
        'to',
        'cc',
        'preview',
        'html',
        'text',
        'body_loaded_at',
        'has_attachments',
        'raw_headers',
    ];

    protected $casts = [
        'message_date' => 'datetime',
        'body_loaded_at' => 'datetime',
        'to' => 'array',
        'cc' => 'array',
        'has_attachments' => 'boolean',
    ];

    public function emails(): BelongsToMany
    {
        return $this->belongsToMany(Email::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailMessageAttachment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MailMessageNote::class)->latest();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_mail_message_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'reply_to_mail_message_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
                ->orWhere('from_address', 'like', "%{$search}%")
                ->orWhere('from_name', 'like', "%{$search}%")
                ->orWhere('message_id', 'like', "%{$search}%")
                ->orWhere('preview', 'like', "%{$search}%")
                ->orWhereHas('emails', fn (Builder $sq) => $sq->where('address', 'like', "%{$search}%"));
        });
    }

    public function scopeFilter(Builder $query, array $filters = []): Builder
    {
        return $query
            ->when(!empty($filters['direction']), function (Builder $q) use ($filters) {
                $q->where('direction', $filters['direction']);
            })
            ->when(!empty($filters['folder']), function (Builder $q) use ($filters) {
                $q->where('folder', $filters['folder']);
            })
            ->when(!empty($filters['mailbox']), function (Builder $q) use ($filters) {
                $q->where('mailbox', mb_strtolower(trim((string) $filters['mailbox'])));
            })
            ->when(!empty($filters['email_id']), function (Builder $q) use ($filters) {
                $q->whereHas('emails', fn (Builder $sq) => $sq->where('emails.id', $filters['email_id']));
            })
            ->when(!empty($filters['email_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('emails', fn (Builder $sq) => $sq->whereIn('emails.id', (array) $filters['email_ids']));
            })
            ->when(!empty($filters['entity_id']), function (Builder $q) use ($filters) {
                $q->whereHas('emails.entities', fn (Builder $sq) => $sq->where('entities.id', $filters['entity_id']));
            })
            ->when(!empty($filters['unit_id']), function (Builder $q) use ($filters) {
                $q->where(function (Builder $nested) use ($filters) {
                    $nested
                        ->whereHas('emails.units', fn (Builder $sq) => $sq->where('units.id', $filters['unit_id']))
                        ->orWhereHas('emails.entities.units', fn (Builder $sq) => $sq->where('units.id', $filters['unit_id']));
                });
            })
            ->when(array_key_exists('has_attachments', $filters) && $filters['has_attachments'] !== null && $filters['has_attachments'] !== '', function (Builder $q) use ($filters) {
                $q->where('has_attachments', filter_var($filters['has_attachments'], FILTER_VALIDATE_BOOLEAN));
            });
    }
}
