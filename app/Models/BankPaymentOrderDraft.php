<?php

namespace App\Models;

use App\Domain\Banking\Enums\PaymentDraftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPaymentOrderDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'status' => PaymentDraftStatus::class,
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'budget_fields' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'payer_bank_account_id');
    }

    public function recipientEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'recipient_entity_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
