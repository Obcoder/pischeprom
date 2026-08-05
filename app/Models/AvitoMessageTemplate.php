<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvitoMessageTemplate extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'general' => 'Общие',
        'greeting' => 'Приветствие',
        'qualification' => 'Уточнение',
        'product' => 'Товары',
        'order' => 'Заказы',
        'delivery' => 'Доставка',
        'follow_up' => 'Повторный контакт',
        'closing' => 'Завершение',
    ];

    protected $fillable = [
        'system_key',
        'name',
        'category',
        'body',
        'is_active',
        'is_favorite',
        'sort_order',
        'usage_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'sort_order' => 'integer',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AvitoMessageTemplateUsage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
