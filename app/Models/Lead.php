<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'source',
        'status',
        'title',
        'description',
        'client_phone',
        'telephone_id',
        'entity_id',
        'unit_id',
        'mail_message_id',
        'assigned_user_id',
        'first_phone_call_id',
        'last_phone_call_id',
        'last_activity_at',
        'closed_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function firstPhoneCall(): BelongsTo
    {
        return $this->belongsTo(PhoneCall::class, 'first_phone_call_id');
    }

    public function lastPhoneCall(): BelongsTo
    {
        return $this->belongsTo(PhoneCall::class, 'last_phone_call_id');
    }

    public function phoneCalls(): HasMany
    {
        return $this->hasMany(PhoneCall::class);
    }

    public function telephone(): BelongsTo
    {
        return $this->belongsTo(Telephone::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('client_phone', 'like', "%{$search}%")
                ->orWhereHas('entity', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('telephone', fn (Builder $sq) => $sq->where('number', 'like', "%{$search}%"));
        });
    }
}
