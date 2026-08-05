<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoConnection extends Model
{
    protected $fillable = [
        'name',
        'auth_mode',
        'external_user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'is_active',
        'last_checked_at',
        'last_error',
        'metadata',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'metadata' => 'encrypted:array',
        ];
    }

    public function apiCalls(): HasMany
    {
        return $this->hasMany(AvitoApiCall::class);
    }
}
