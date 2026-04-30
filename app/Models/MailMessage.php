<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MailMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox',
        'folder',
        'direction',
        'imap_uid',
        'message_id',
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
            ->when(!empty($filters['email_id']), function (Builder $q) use ($filters) {
                $q->whereHas('emails', fn (Builder $sq) => $sq->where('emails.id', $filters['email_id']));
            });
    }
}
