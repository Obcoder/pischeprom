<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CommodityMedia extends Model
{
    use HasFactory;

    protected $table = 'commodity_media';

    protected $fillable = [
        'commodity_id',
        'disk',
        'path',
        'filename',
        'original_name',
        'mime_type',
        'size',
        'is_ava',
        'sort_order',
    ];

    protected $casts = [
        'is_ava' => 'boolean',
        'size' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'url',
    ];

    public function commodity()
    {
        return $this->belongsTo(Commodity::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk ?: config('filesystems.unit_files_disk', 'yandex'))
            ->url($this->path);
    }
}
