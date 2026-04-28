<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Email extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'address',
        'name',
        'comment',
        'source',
        'is_active',
        'verified_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Email $email) {
            if ($email->address) {
                $email->address = Str::lower(trim($email->address));
            }
        });
    }

    public function sendings(): HasMany
    {
        return $this->hasMany(Sending::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)->withTimestamps();
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)->withTimestamps();
    }

    public function mailMessages(): BelongsToMany
    {
        return $this->belongsToMany(MailMessage::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function sentMailMessages(): BelongsToMany
    {
        return $this->mailMessages()
            ->where('mail_messages.direction', 'outgoing')
            ->wherePivotIn('role', ['to', 'cc']);
    }

    public function receivedMailMessages(): BelongsToMany
    {
        return $this->mailMessages()
            ->where('mail_messages.direction', 'incoming')
            ->wherePivot('role', 'from');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('address', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('domain', 'like', "%{$search}%")
                ->orWhere('comment', 'like', "%{$search}%")
                ->orWhereHas('units', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('entities', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('sendings', fn (Builder $sq) => $sq->where('subject', 'like', "%{$search}%"));
        });
    }

    public function scopeFilter(Builder $query, array $filters = []): Builder
    {
        $isActive = array_key_exists('is_active', $filters)
            ? filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $hasIncoming = filter_var($filters['has_incoming'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasOutgoing = filter_var($filters['has_outgoing'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $query
            ->when($isActive !== null, function (Builder $q) use ($isActive) {
                $q->where('is_active', $isActive);
            })
            ->when(!empty($filters['domain']), function (Builder $q) use ($filters) {
                $q->where('domain', $filters['domain']);
            })
            ->when(!empty($filters['unit_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('units', fn (Builder $sq) => $sq->whereIn('units.id', (array) $filters['unit_ids']));
            })
            ->when(!empty($filters['entity_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('entities', fn (Builder $sq) => $sq->whereIn('entities.id', (array) $filters['entity_ids']));
            })
            ->when($hasIncoming, function (Builder $q) {
                $q->has('receivedMailMessages');
            })
            ->when($hasOutgoing, function (Builder $q) {
                $q->has('sentMailMessages');
            });
    }

    public function scopeWithEmailStats(Builder $query): Builder
    {
        return $query
            ->withCount([
                            'units',
                            'entities',
                            'sendings',
                            'sentMailMessages as sent_count',
                            'receivedMailMessages as received_count',
                        ])
            ->withMax('sentMailMessages as last_sent_at', 'message_date')
            ->withMax('receivedMailMessages as last_received_at', 'message_date');
    }

    public function scopeApplySort(Builder $query, ?string $sortBy = null, bool $sortDesc = false): Builder
    {
        $direction = $sortDesc ? 'desc' : 'asc';

        return match ($sortBy) {
            'address' => $query->orderBy('address', $direction),
            'name' => $query->orderBy('name', $direction),
            'domain' => $query->orderBy('domain', $direction),
            'is_active' => $query->orderBy('is_active', $direction),
            'created_at' => $query->orderBy('created_at', $direction),
            'sendings_count' => $query->orderBy('sendings_count', $direction),
            'sent_count' => $query->orderBy('sent_count', $direction),
            'received_count' => $query->orderBy('received_count', $direction),
            'last_sent_at' => $query->orderBy('last_sent_at', $direction),
            'last_received_at' => $query->orderBy('last_received_at', $direction),
            default => $query->orderByDesc('last_seen_at')->orderBy('address'),
        };
    }
}
