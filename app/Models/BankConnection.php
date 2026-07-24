<?php

namespace App\Models;

use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Enums\BankEnvironment;
use App\Domain\Banking\Enums\BankProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankConnection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => BankProvider::class,
            'environment' => BankEnvironment::class,
            'status' => BankConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'scopes' => 'array',
            'connected_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'last_error_at' => 'datetime',
            'client_secret_expires_at' => 'datetime',
            'mtls_certificate_expires_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'owner_entity_id');
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(BankSyncRun::class);
    }
}
