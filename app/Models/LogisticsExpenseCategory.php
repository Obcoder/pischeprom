<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'logistics_expense_categories';

    protected $fillable = ['code', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(LogisticsTripExpense::class, 'expense_category_id');
    }
}
