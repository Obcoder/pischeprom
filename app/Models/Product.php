<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'rus',
        'eng',
        'zh',
        'es',
    ];

    protected $with = [
        'consumers',
    ];

    public function units()
    {
        return $this->belongsToMany(Unit::class)
            ->using(product_unit::class)
            ->withPivot(['action_id'])
            ->withTimestamps();
    }
    public function consumers()
    {
        return $this->hasManyThrough(
            Unit::class, // Модель конечной таблицы
            'consumptions', // Промежуточная таблица
            'product_id', // Внешний ключ в `consumptions`, который ссылается на `products`
            'id', // Внешний ключ в `units`, который связывается с `consumptions.unit_id`
            'id', // Локальный ключ в `products`
            'unit_id' // Локальный ключ в `consumptions`
        );
    }
}
