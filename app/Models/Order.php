<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'number',
        'entity_id',
        'order_status_id',
        'created_by_user_id',
        'contact_telephone_id',
        'preferred_delivery_time',
        'internal_comment',
        'total_amount',
        'total_weight',
        'currency_code',
        'submitted_at',
        'notified_at',
        'closed_at',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'total_weight' => 'float',
        'submitted_at' => 'datetime',
        'notified_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->number)) {
                $order->number = static::generateNumber();
            }

            if (! $order->order_status_id) {
                $order->order_status_id = OrderStatus::query()
                    ->where('code', OrderStatus::OPEN)
                    ->value('id');
            }

            $order->submitted_at ??= now();
        });
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'PP-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function contactTelephone(): BelongsTo
    {
        return $this->belongsTo(Telephone::class, 'contact_telephone_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class, 'order_items')
            ->withPivot([
                'id',
                'good_name',
                'quantity',
                'denominator',
                'line_weight',
                'price_gross',
                'currency_code',
                'line_total',
            ])
            ->withTimestamps();
    }

    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class)
            ->withPivot(['role', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function avitoChats(): BelongsToMany
    {
        return $this->belongsToMany(AvitoChat::class, 'avito_chat_order')
            ->withPivot('source_message_id')
            ->withTimestamps();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($search): void {
            $nested
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('entity', fn (Builder $entity) => $entity->where('name', 'like', "%{$search}%"))
                ->orWhereHas('items', fn (Builder $item) => $item->where('good_name', 'like', "%{$search}%"))
                ->orWhereHas('buildings', fn (Builder $building) => $building->where('address', 'like', "%{$search}%"));
        });
    }
}
