<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'amount',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount' => 'float',
    ];

    protected $with = [
        'entity',
        'goods',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class, 'good_purchase')
            ->withPivot($this->goodPurchasePivotColumns())
            ->withTimestamps();
    }

    private function goodPurchasePivotColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $desired = [
            'id',
            'quantity',
            'measure_id',
            'price',
            'currency_id',
            'total',
            'created_at',
            'updated_at',
        ];

        $available = Schema::hasTable('good_purchase')
            ? array_flip(Schema::getColumnListing('good_purchase'))
            : [];

        return $columns = array_values(array_filter(
            $desired,
            fn (string $column) => isset($available[$column])
        ));
    }
}
