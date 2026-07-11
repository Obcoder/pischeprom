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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
                    ->orWhere('services.code', 'like', "%{$search}%")
                    ->orWhere('services.description', 'like', "%{$search}%");
            });
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

    public function scopeByExpenseArticle(Builder $query, mixed $expenseArticleId): Builder
    {
        return $query->when($expenseArticleId, function (Builder $query) use ($expenseArticleId) {
            $query->where('services.expense_article_id', $expenseArticleId);
        });
    }

    public function scopeByProject(Builder $query, mixed $projectId): Builder
    {
        return $query->when($projectId, function (Builder $query) use ($projectId) {
            $query->where('services.project_id', $projectId);
        });
    }

    public function scopeActiveState(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === 'all') {
            return $query;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool === null ? $query : $query->where('services.is_active', $bool);
    }

    public function scopeCreatedFrom(Builder $query, ?string $date): Builder
    {
        return $query->when($date, function (Builder $query) use ($date) {
            $query->whereDate('services.created_at', '>=', $date);
        });
    }

    public function scopeCreatedTo(Builder $query, ?string $date): Builder
    {
        return $query->when($date, function (Builder $query) use ($date) {
            $query->whereDate('services.created_at', '<=', $date);
        });
    }

    public function scopeApplySort(Builder $query, ?string $sortBy, mixed $sortDesc = false): Builder
    {
        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN)
            ? 'desc'
            : 'asc';

        match ($sortBy) {
            'expense_article_id' => $query->orderBy(
                ExpenseArticle::select('name')
                    ->whereColumn('expense_articles.id', 'services.expense_article_id'),
                $direction
            ),
            'project_id' => $query->orderBy(
                Project::select('name')
                    ->whereColumn('projects.id', 'services.project_id'),
                $direction
            ),
            default => $query->orderBy($this->sortableColumn($sortBy), $direction),
        };

        return $query->orderBy('services.id');
    }

    private function sortableColumn(?string $sortBy): string
    {
        return [
            'id' => 'services.id',
            'name' => 'services.name',
            'code' => 'services.code',
            'is_active' => 'services.is_active',
            'checks_count' => 'checks_count',
            'created_at' => 'services.created_at',
            'updated_at' => 'services.updated_at',
        ][$sortBy] ?? 'services.name';
    }
}
