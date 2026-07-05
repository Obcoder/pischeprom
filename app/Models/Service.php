<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'expense_article_id',
        'project_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function checks(): BelongsToMany
    {
        return $this->belongsToMany(Check::class, 'check_service')
            ->using(CheckService::class)
            ->withPivot('id', 'quantity', 'measure_id', 'expense_article_id', 'price', 'total_price')
            ->withTimestamps();
    }

    public function checkItems(): HasMany
    {
        return $this->hasMany(CheckService::class);
    }

    public function expenseArticle(): BelongsTo
    {
        return $this->belongsTo(ExpenseArticle::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $search = trim($search);

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('services.name', 'like', "%{$search}%")
                    ->orWhere('services.code', 'like', "%{$search}%");
            });
        });
    }
}
