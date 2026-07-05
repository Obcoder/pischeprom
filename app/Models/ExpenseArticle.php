<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function commodities(): HasMany
    {
        return $this->hasMany(Commodity::class);
    }

    public function checkCommodities(): HasMany
    {
        return $this->hasMany(CheckCommodity::class);
    }
}
