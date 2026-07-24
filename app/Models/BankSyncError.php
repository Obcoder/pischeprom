<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankSyncError extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requires_intervention' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(BankSyncRun::class, 'bank_sync_run_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
