<?php

namespace App\Models;

use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class BankTransaction extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'raw_payload',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Imported bank transactions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'posting_date' => 'date',
            'value_date' => 'date',
            'direction' => BankTransactionDirection::class,
            'amount' => 'decimal:2',
            'status' => BankTransactionStatus::class,
            'bank_modified_at' => 'datetime',
            'raw_payload' => 'encrypted:array',
            'imported_at' => 'datetime',
            'reconciliation_status' => ReconciliationStatus::class,
            'no_reconciliation_required' => 'boolean',
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

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BankTransactionRevision::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(BankMatchSuggestion::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BankTransactionAllocation::class);
    }

    public function activeAllocations(): HasMany
    {
        return $this->allocations()->where('is_active', true);
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('direction', BankTransactionDirection::Credit->value);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', BankTransactionStatus::Posted->value);
    }
}
