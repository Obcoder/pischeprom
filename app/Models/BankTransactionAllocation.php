<?php

namespace App\Models;

use App\Domain\Banking\Enums\AllocationSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class BankTransactionAllocation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Bank transaction allocations must be reversed, not deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'source' => AllocationSource::class,
            'is_active' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
