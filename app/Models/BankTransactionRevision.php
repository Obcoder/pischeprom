<?php

namespace App\Models;

use App\Domain\Banking\Enums\BankTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BankTransactionRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['raw_payload'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Bank transaction revisions are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Bank transaction revisions are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BankTransactionStatus::class,
            'changed_fields' => 'array',
            'raw_payload' => 'encrypted:array',
            'recorded_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }
}
