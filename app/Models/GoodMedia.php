<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodMedia extends Model
{
    protected $table = 'good_media';

    protected $fillable = [
        'good_id',
        'folder_id',
        'type',
        'disk',
        'path',
        'url',
        'thumb_path',
        'thumb_url',
        'original_name',
        'file_name',
        'mime_type',
        'size',
        'extension',
        'title',
        'alt',
        'caption',
        'sort_order',
        'is_published',
        'is_ava',
        'is_processed',
        'processing_status',
        'processing_error',
        'poster_path',
        'poster_url',
        'video_mp4_path',
        'video_mp4_url',
        'video_hls_path',
        'video_hls_url',
        'width',
        'height',
        'duration_seconds',
        'meta',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_ava' => 'boolean',
        'is_processed' => 'boolean',
        'meta' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'float',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(GoodMediaFolder::class, 'folder_id');
    }
}
