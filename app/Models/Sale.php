<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'payment_reference',
        'total',
    ];

    protected $with = ['entity'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'overpaid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function setTotalAttribute(mixed $value): void
    {
        $this->attributes['total'] = $value;

        if ($this->exists) {
            return;
        }

        $this->attributes['payment_status'] ??= 'unpaid';
        $this->attributes['paid_amount'] ??= '0.00';
        $this->attributes['outstanding_amount'] ??= $value;
        $this->attributes['overpaid_amount'] ??= '0.00';
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class)
            ->withPivot('quantity', 'price', 'measure_id', 'total');
    }

    public function bankAllocations(): MorphMany
    {
        return $this->morphMany(BankTransactionAllocation::class, 'allocatable');
    }

    public function activeBankAllocations(): MorphMany
    {
        return $this->bankAllocations()->where('is_active', true);
    }

    /**
     * Scope для фильтрации по продукту
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->whereHas('goods.products', function (Builder $q) use ($productId) {
            $q->where('products.id', $productId);
        });
    }
}
