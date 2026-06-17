<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MailMessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_message_id',
        'disk',
        'path',
        'original_name',
        'file_name',
        'mime_type',
        'size',
        'content_id',
        'disposition',
        'saved_to_disk_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'saved_to_disk_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'folder_path',
        'folder_url',
        'url',
    ];

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->disk || ! $this->path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->url($this->path);
        } catch (Throwable) {
            return null;
        }
    }

    public function getFolderPathAttribute(): ?string
    {
        if (! $this->path || ! str_contains($this->path, '/')) {
            return null;
        }

        return trim(dirname($this->path), '/.');
    }

    public function getFolderUrlAttribute(): ?string
    {
        if (! $this->disk || ! $this->folder_path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->url(rtrim($this->folder_path, '/') . '/');
        } catch (Throwable) {
            return null;
        }
    }
}
