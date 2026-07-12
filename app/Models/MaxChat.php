<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaxChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'phone_normalized',
        'chat_id',
        'user_id',
        'entity_id',
        'unit_id',
        'contact_name',
        'title',
        'source_type',
        'is_active',
        'last_message_at',
        'last_payload',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_message_at' => 'datetime',
        'last_payload' => 'array',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MaxMessage::class)
            ->latest('created_at');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $phone = self::normalizePhone($search);

        return $query->where(function (Builder $q) use ($search, $phone): void {
            $q->where('phone', 'like', "%{$search}%")
                ->orWhere('phone_normalized', 'like', "%{$phone}%")
                ->orWhere('chat_id', 'like', "%{$search}%")
                ->orWhere('user_id', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('entity', fn (Builder $eq) => $eq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('unit', fn (Builder $uq) => $uq->where('name', 'like', "%{$search}%"));
        });
    }

    public function scopeForPhone(Builder $query, mixed $phone): Builder
    {
        $normalized = self::normalizePhone($phone);

        return $query->when($normalized, fn (Builder $q) => $q->where('phone_normalized', $normalized));
    }

    public static function normalizePhone(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->first(fn ($item) => filled($item));
        }

        $phone = preg_replace('/\D+/', '', (string) $value);

        if ($phone === '') {
            return null;
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
            return '7'.substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            return '7'.$phone;
        }

        return $phone;
    }
}
