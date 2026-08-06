<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvitoAutoReplyRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'description',
        'response_text',
        'is_active',
        'is_approved',
        'is_pilot',
        'confidence_threshold',
        'cooldown_minutes',
        'daily_limit',
        'account_ids',
        'context_ids',
        'version',
        'sort_order',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'is_pilot' => 'boolean',
            'confidence_threshold' => 'float',
            'cooldown_minutes' => 'integer',
            'daily_limit' => 'integer',
            'account_ids' => 'array',
            'context_ids' => 'array',
            'version' => 'integer',
            'sort_order' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function examples(): HasMany
    {
        return $this->hasMany(AvitoAutoReplyExample::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(AvitoAutoReplyDecision::class);
    }

    public function scopeEligible(Builder $query, string $mode): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_approved', true)
            ->when($mode === 'pilot', fn (Builder $query) => $query->where('is_pilot', true));
    }

    public function appliesTo(?AvitoChat $chat): bool
    {
        if (! $chat) {
            return true;
        }

        $accountIds = array_map('intval', $this->account_ids ?: []);
        if ($accountIds !== [] && ! in_array($chat->avito_messenger_account_id, $accountIds, true)) {
            return false;
        }

        $contextIds = array_map('strval', $this->context_ids ?: []);

        return $contextIds === [] || in_array((string) $chat->context_id, $contextIds, true);
    }
}
