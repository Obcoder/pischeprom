<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoAutoloadFeed extends Model
{
    protected $fillable = [
        'avito_account_id',
        'avito_connection_id',
        'name',
        'access_token',
        'profile_status',
        'defaults',
        'profile_snapshot',
        'last_upload_snapshot',
        'last_error',
        'profile_checked_at',
        'profile_attached_at',
        'last_upload_requested_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'profile_snapshot',
        'last_upload_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'avito_account_id' => 'integer',
            'avito_connection_id' => 'integer',
            'access_token' => 'encrypted',
            'defaults' => 'encrypted:array',
            'profile_snapshot' => 'encrypted:array',
            'last_upload_snapshot' => 'encrypted:array',
            'profile_checked_at' => 'datetime',
            'profile_attached_at' => 'datetime',
            'last_upload_requested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AvitoConnection::class, 'avito_connection_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(AvitoPublication::class);
    }
}
