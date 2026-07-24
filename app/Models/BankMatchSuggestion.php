<?php

namespace App\Models;

use App\Domain\Banking\Enums\MatchSuggestionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankMatchSuggestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'rules' => 'array',
            'status' => MatchSuggestionStatus::class,
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function suggestable(): MorphTo
    {
        return $this->morphTo();
    }
}
