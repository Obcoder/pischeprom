<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoMessageAttachment extends Model
{
    protected $fillable = [
        'avito_message_id',
        'kind',
        'external_id',
        'remote_url',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'archive_attempts',
        'archived_at',
        'last_attempted_at',
        'archive_error',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AvitoMessage::class, 'avito_message_id');
    }
}
