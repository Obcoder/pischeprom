<?php

namespace App\Models;

use App\Domain\Banking\Enums\BankSyncRunStatus;
use App\Domain\Banking\Enums\BankSyncType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => BankSyncType::class,
            'status' => BankSyncRunStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(BankSyncError::class);
    }
}
