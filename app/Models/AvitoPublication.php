<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AvitoPublication extends Model
{
    protected $fillable = [
        'avito_autoload_feed_id',
        'good_id',
        'avito_connection_id',
        'avito_account_id',
        'avito_item_id',
        'external_id',
        'status',
        'draft_dirty',
        'category_node_slug',
        'category_name',
        'draft_payload',
        'validation_errors',
        'last_remote_report',
        'last_error',
        'approved_at',
        'last_upload_requested_at',
        'published_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'draft_payload',
        'last_remote_report',
    ];

    protected function casts(): array
    {
        return [
            'avito_autoload_feed_id' => 'integer',
            'good_id' => 'integer',
            'avito_connection_id' => 'integer',
            'avito_account_id' => 'integer',
            'avito_item_id' => 'integer',
            'draft_dirty' => 'boolean',
            'draft_payload' => 'encrypted:array',
            'validation_errors' => 'array',
            'last_remote_report' => 'encrypted:array',
            'approved_at' => 'datetime',
            'last_upload_requested_at' => 'datetime',
            'published_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(AvitoAutoloadFeed::class, 'avito_autoload_feed_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AvitoConnection::class, 'avito_connection_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AvitoPublicationRevision::class);
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(AvitoPublicationRevision::class)->where('is_current', true);
    }
}
