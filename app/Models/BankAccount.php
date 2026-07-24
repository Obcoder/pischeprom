<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'account_number',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'balance_as_of' => 'datetime',
            'balance_statement_date' => 'date',
            'last_synced_at' => 'datetime',
            'last_incremental_cursor_at' => 'datetime',
            'normalized_requisites' => 'array',
            'raw_payload' => 'encrypted:array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(BankAccountBalanceSnapshot::class);
    }

    public function paymentDrafts(): HasMany
    {
        return $this->hasMany(BankPaymentOrderDraft::class, 'payer_bank_account_id');
    }
}
