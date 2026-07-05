<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Commodity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ava',
        'expense_article_id',
        'project_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'ava_url',
    ];

    public function checks()
    {
        return $this->belongsToMany(Check::class, 'check_commodity')
            ->using(CheckCommodity::class)
            ->withPivot('id', 'quantity', 'measure_id', 'expense_article_id', 'warehouse_id', 'price', 'total_price')
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function expenseArticle(): BelongsTo
    {
        return $this->belongsTo(ExpenseArticle::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function media()
    {
        return $this->hasMany(CommodityMedia::class)
            ->orderByDesc('is_ava')
            ->orderBy('sort_order')
            ->orderByDesc('created_at');
    }

    public function avaMedia()
    {
        return $this->hasOne(CommodityMedia::class)
            ->where('is_ava', true);
    }

    public function getAvaUrlAttribute(): ?string
    {
        if (! $this->ava) {
            return null;
        }

        return Storage::disk(config('filesystems.unit_files_disk', 'yandex'))
            ->url($this->ava);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where('commodities.name', 'like', '%'.trim($search).'%');
        });
    }

    public function scopeByCheck(Builder $query, mixed $checkId): Builder
    {
        return $query->when($checkId, function (Builder $query) use ($checkId) {
            $query->whereHas('checks', function (Builder $query) use ($checkId) {
                $query->where('checks.id', $checkId);
            });
        });
    }

    public function scopeHasAva(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === 'all') {
            return $query;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($bool === true) {
            return $query
                ->whereNotNull('commodities.ava')
                ->where('commodities.ava', '!=', '');
        }

        if ($bool === false) {
            return $query->where(function (Builder $query) {
                $query
                    ->whereNull('commodities.ava')
                    ->orWhere('commodities.ava', '');
            });
        }

        return $query;
    }

    public function scopeCreatedFrom(Builder $query, ?string $date): Builder
    {
        return $query->when($date, function (Builder $query) use ($date) {
            $query->whereDate('commodities.created_at', '>=', $date);
        });
    }

    public function scopeCreatedTo(Builder $query, ?string $date): Builder
    {
        return $query->when($date, function (Builder $query) use ($date) {
            $query->whereDate('commodities.created_at', '<=', $date);
        });
    }

    public function scopeApplySort(Builder $query, ?string $sortBy, mixed $sortDesc = true): Builder
    {
        $sortable = [
            'id' => 'commodities.id',
            'name' => 'commodities.name',
            'ava' => 'commodities.ava',
            'expense_article_id' => 'commodities.expense_article_id',
            'project_id' => 'commodities.project_id',
            'created_at' => 'commodities.created_at',
            'updated_at' => 'commodities.updated_at',
            'checks_count' => 'checks_count',
            'media_count' => 'media_count',
        ];

        $column = $sortable[$sortBy] ?? 'commodities.created_at';

        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN)
            ? 'desc'
            : 'asc';

        return $query->orderBy($column, $direction);
    }
}
