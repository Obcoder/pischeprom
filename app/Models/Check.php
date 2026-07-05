<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    protected $with = [
        'entity',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function commodities()
    {
        return $this->belongsToMany(Commodity::class, 'check_commodity')
            ->using(CheckCommodity::class)
            ->withPivot('id', 'quantity', 'measure_id', 'expense_article_id', 'price', 'total_price')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckCommodity::class)
            ->with([
                'commodity.avaMedia',
                'commodity.expenseArticle',
                'commodity.project',
                'expenseArticle',
                'measure',
            ])
            ->orderBy('id');
    }
}
