<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'rus',
        'eng',
        'zh',
        'es',
        'category_id',
    ];
    protected $with = [
        'category',
        'manufacturers',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class)
            ->using(product_unit::class)
            ->withPivot(['action_id'])
            ->withTimestamps();
    }
    public function consumers()
    {
        return $this->hasMany(
            Consumption::class, // Модель конечной таблицы
            'product_id', // Промежуточная таблица
            'id', // Внешний ключ в `consumptions`, который ссылается на `products`
            'unit_id', // Внешний ключ в `units`, который связывается с `consumptions.unit_id`
            'id', // Локальный ключ в `products`
            'id' // Локальный ключ в `consumptions`
        );
    }
    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class);
    }
    public function manufacturers(): BelongsToMany
    {
        return $this->BelongsToMany(Unit::class, 'manufacturers', 'product_id', 'unit_id');
    }
}
