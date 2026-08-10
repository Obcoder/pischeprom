<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoWorkspaceSetting extends Model
{
    public const AUTH_MODES = ['server', 'oauth'];

    protected $fillable = [
        'auth_mode',
        'default_account_id',
        'default_connection_id',
        'server_account_id',
        'server_account_name',
        'last_error',
        'server_account_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'default_account_id' => 'integer',
            'default_connection_id' => 'integer',
            'server_account_id' => 'integer',
            'server_account_checked_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'auth_mode' => 'server',
        ]);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AvitoConnection::class, 'default_connection_id');
    }
}
